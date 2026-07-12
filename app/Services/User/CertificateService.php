<?php

namespace App\Services\User;

use App\Mail\EnrollmentCertificateMail;
use App\Models\Certificate;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Notifications\CertificateReady;
use App\Services\Pdf\DompdfExportService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CertificateService
{
    public function __construct(
        private DompdfExportService $pdfExporter
    ) {}

    /**
     * Stream the certificate file inline (for in-browser iframe preview).
     */
    public function viewFile(Certificate $certificate): StreamedResponse
    {
        return $this->streamCertificateFile($certificate->certificate_url);
    }

    /**
     * Stream the certificate file inline so the client can build the
     * download blob itself.
     */
    public function downloadFile(Certificate $certificate): StreamedResponse
    {
        return $this->streamCertificateFile($certificate->certificate_url);
    }

    /**
     * Shared file streamer.
     */
    private function streamCertificateFile(?string $path): StreamedResponse
    {
        if (empty($path) || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Certificate file not found on server.');
        }

        return Storage::disk('public')->response($path, null, [
            'Content-Type' => 'text/plain',
            'X-Download-Options' => 'noopen',
            'Content-Disposition' => 'inline',
            'Access-Control-Allow-Origin' => config('app.frontend_url'),
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Expose-Headers' => 'Content-Disposition, Content-Length',
        ]);
    }

    public function studentHasReviewForEnrollment(Enrollment $enrollment): bool
    {
        return CourseReview::query()
            ->where('course_id', $enrollment->course_id)
            ->where('student_id', $enrollment->student_id)
            ->exists();
    }

    public function enrollmentCanIssueCertificate(Enrollment $enrollment): bool
    {
        if (! $enrollment->is_completed) {
            return false;
        }

        if ($this->studentHasReviewForEnrollment($enrollment)) {
            return true;
        }

        return Certificate::query()
            ->where('student_id', $enrollment->student_id)
            ->where('course_id', $enrollment->course_id)
            ->exists();
    }

    /**
     * Issue a new certificate for an enrollment
     */
    public function issueCertificate(Enrollment $enrollment, bool $force = false)
    {
        // Load relationships correctly
        $enrollment->loadMissing(['student', 'course.instructors', 'course.tags']);

        $existingCert = Certificate::query()
            ->where('student_id', $enrollment->student_id)
            ->where('course_id', $enrollment->course_id)
            ->first();

        if ($existingCert && ! $force) {
            return $existingCert;
        }

        if (! $existingCert && ! $this->studentHasReviewForEnrollment($enrollment)) {
            throw new AccessDeniedHttpException(
                'A review must be submitted before a certificate can be issued.'
            );
        }

        if ($existingCert && $force) {
            if (! empty($existingCert->certificate_url) && Storage::disk('public')->exists($existingCert->certificate_url)) {
                Storage::disk('public')->delete($existingCert->certificate_url);
            }
            $existingCert->delete();
        }

        $fileName = 'certificates/cert_'.Str::random(16).'.pdf';

        // Get instructor name safely
        $instructorName = $this->getInstructorName($enrollment->course);

        // Generate PDF data
        $pdfData = $this->preparePdfData([
            'name' => $enrollment->student->full_name,
            'course' => $enrollment->course->title,
            'instructor_name' => $instructorName,
            'tags' => $this->extractCourseTags($enrollment->course),
        ]);

        // Generate and save PDF
        $pdf = $this->certificatePdfData($pdfData);
        $pdfContent = $pdf->output();
        Storage::disk('public')->put($fileName, $pdfContent);

        $certificateNum = 'TSQ-'.date('Y').'-'.strtoupper(Str::random(8));

        return Certificate::create([
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'certificate_url' => $fileName,
            'certificate_num' => $certificateNum,
            'issued_at' => now(),
        ]);
    }

    public function issueCertificateAndNotify(Enrollment $enrollment): bool
    {
        try {
            $certificate = $this->issueCertificate($enrollment);
            $enrollment->loadMissing(['student.user', 'course']);

            $email = $enrollment->student?->user?->email;

            if ($email) {
                Mail::to($email)->send(new EnrollmentCertificateMail($enrollment, $certificate->certificate_url));

                $user = $enrollment->student?->user;
                if ($user && ! $this->userAlreadyNotifiedAboutCertificate($user, $enrollment->id)) {
                    $user->notify(new CertificateReady($enrollment));
                }

                Log::info('Certificate email sent after review submission', [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'email' => $email,
                ]);
            } else {
                Log::warning('Certificate email skipped after review: no user email', [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Certificate generation/email failed after review: '.$e->getMessage(), [
                'enrollment_id' => $enrollment->id,
            ]);

            return false;
        }
    }

    /**
     * Generate live certificate for download
     */
    public function generateLiveCertificate(Enrollment $enrollment)
    {
        // Load relationships correctly
        $enrollment->loadMissing(['student', 'course.instructors', 'course.tags']);

        // Get instructor name safely
        $instructorName = $this->getInstructorName($enrollment->course);

        // Generate PDF data
        $pdfData = $this->preparePdfData([
            'name' => $enrollment->student->full_name,
            'course' => $enrollment->course->title,
            'instructor_name' => $instructorName,
            'tags' => $this->extractCourseTags($enrollment->course),
        ]);

        return $this->certificatePdfData($pdfData)
            ->name('certificate-'.$enrollment->id.'.pdf');
    }

    /**
     * Generate binary PDF content for API responses
     */
    public function generateBinaryPdf($attempt): string
    {
        // Load relationships correctly for ExamAttempt
        $attempt->loadMissing(['student', 'exam.course.instructors', 'exam.course.tags']);

        // Get instructor name safely
        $instructorName = $this->getInstructorName($attempt->exam->course ?? null);

        // Prepare PDF data
        $pdfData = $this->preparePdfData([
            'name' => $attempt->student->full_name,
            'course' => $attempt->exam->course->title ?? 'Course',
            'instructor_name' => $instructorName,
            'tags' => $this->extractCourseTags($attempt->exam->course ?? null),
        ]);

        // Generate PDF to temporary file
        $temporaryPath = tempnam(sys_get_temp_dir(), 'certificate_').'.pdf';
        $this->certificatePdfData($pdfData)->save($temporaryPath);

        $pdfContent = file_get_contents($temporaryPath);
        @unlink($temporaryPath);

        return $pdfContent;
    }

    /**
     * Prepare PDF data with all required fields and defaults
     */
    private function preparePdfData(array $customData = []): array
    {
        $logoData = Cache::remember('certificate_logo_base64', now()->addDay(), function () {
            $logoPath = public_path('image/logo-dark.png');

            return is_file($logoPath)
                ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
                : null;
        });

        // Default data structure
        $defaultData = [
            'name' => '',
            'course' => '',
            'date' => now()->format('F Y'),
            'tags' => null,
            'instructor_name' => 'Instructor',
            'logoData' => $logoData,
        ];

        // Merge custom data with defaults
        $data = array_merge($defaultData, $customData);

        // Ensure all required fields are present and valid
        foreach (['name', 'course'] as $key) {
            if (empty($data[$key])) {
                throw new \InvalidArgumentException("Certificate PDF data missing or invalid for key: {$key}");
            }
        }

        return $data;
    }

    /**
     * Generate PDF instance from view and data
     */
    private function certificatePdfData(array $data)
    {
        try {
            return $this->pdfExporter->loadView('certificate', $data, 'a4', 'landscape');
        } catch (\Throwable $e) {
            throw new \Exception('Failed to generate certificate PDF: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract course tags safely
     */
    private function extractCourseTags($course)
    {
        if (! $course) {
            return null;
        }

        if (! $course->relationLoaded('tags')) {
            $course->load('tags');
        }

        $tags = $course->tags ?? null;
        if (! $tags || $tags->isEmpty()) {
            return null;
        }

        return $tags->pluck('name')->all();
    }

    /**
     * Get instructor name safely from course
     */
    private function getInstructorName($course): string
    {
        if (! $course) {
            return 'Instructor';
        }

        $course->loadMissing('instructors');

        if ($course->relationLoaded('instructors') && $course->instructors->isNotEmpty()) {
            return $course->instructors->pluck('full_name')->filter()->join(', ');
        }

        $course->loadMissing('instructor');

        return $course->instructor?->full_name ?? 'Instructor';
    }

    private function userAlreadyNotifiedAboutCertificate(object $user, int $enrollmentId): bool
    {
        return $user->notifications()
            ->where('type', CertificateReady::class)
            ->where('data->enrollment_id', $enrollmentId)
            ->exists();
    }
}
