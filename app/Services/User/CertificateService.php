<?php

namespace App\Services\User;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class CertificateService
{
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

    /**
     * Issue a new certificate for an enrollment
     */
    public function issueCertificate(Enrollment $enrollment, bool $force = false)
    {
        // Load relationships correctly
        $enrollment->loadMissing(['student', 'course.instructor', 'course.tags']);

        $existingCert = Certificate::query()
            ->where('student_id', $enrollment->student_id)
            ->where('course_id', $enrollment->course_id)
            ->first();

        if ($existingCert && ! $force) {
            return $existingCert;
        }

        if ($existingCert && $force) {
            if (! empty($existingCert->certificate_url) && Storage::disk('public')->exists($existingCert->certificate_url)) {
                Storage::disk('public')->delete($existingCert->certificate_url);
            }
            $existingCert->delete();
        }

        $fileName = 'certificates/cert_' . Str::random(16) . '.pdf';
        
        // Get instructor name safely
        $instructorName = $this->getInstructorName($enrollment->course);
        
        // Generate PDF data
        $pdfData = $this->preparePdfData([
            'name'            => $enrollment->student->full_name,
            'course'          => $enrollment->course->title,
            'instructor_name' => $instructorName,
            'tags'            => $this->extractCourseTags($enrollment->course),
        ]);
        
        // Generate and save PDF
        $pdf = $this->certificatePdfData($pdfData);
        $pdfContent = $pdf->output();
        Storage::disk('public')->put($fileName, $pdfContent);

        $certificateNum = 'TSQ-' . date('Y') . '-' . strtoupper(Str::random(8));

        return Certificate::create([
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'certificate_url' => $fileName,
            'certificate_num' => $certificateNum,
            'issued_at' => now(),
        ]);
    }

    /**
     * Generate live certificate for download
     */
    public function generateLiveCertificate(Enrollment $enrollment)
    {
        // Load relationships correctly
        $enrollment->loadMissing(['student', 'course.instructor', 'course.tags']);
        
        // Get instructor name safely
        $instructorName = $this->getInstructorName($enrollment->course);
        
        // Generate PDF data
        $pdfData = $this->preparePdfData([
            'name'            => $enrollment->student->full_name,
            'course'          => $enrollment->course->title,
            'instructor_name' => $instructorName,
            'tags'            => $this->extractCourseTags($enrollment->course),
        ]);

        return $this->certificatePdfData($pdfData)
                    ->name('certificate-' . $enrollment->id . '.pdf');
    }

    /**
     * Generate binary PDF content for API responses
     */
    public function generateBinaryPdf($attempt): string
    {
        // Load relationships correctly for ExamAttempt
        $attempt->loadMissing(['student', 'exam.course.instructor', 'exam.course.tags']);
        
        // Get instructor name safely
        $instructorName = $this->getInstructorName($attempt->exam->course ?? null);
        
        // Prepare PDF data
        $pdfData = $this->preparePdfData([
            'name'            => $attempt->student->full_name,
            'course'          => $attempt->exam->course->title ?? 'Course',
            'instructor_name' => $instructorName,
            'tags'            => $this->extractCourseTags($attempt->exam->course ?? null),
        ]);

        // Generate PDF to temporary file
        $temporaryPath = tempnam(sys_get_temp_dir(), 'certificate_') . '.pdf';
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
                ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
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
            return Pdf::loadView('certificate', $data)
                      ->setPaper('a4', 'landscape');
        } catch (\Throwable $e) {
            throw new \Exception('Failed to generate certificate PDF: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract course tags safely
     */
    private function extractCourseTags($course)
    {
        if (! $course) return null;
        
        if (! $course->relationLoaded('tags')) {
            $course->load('tags');
        }
        
        $tags = $course->tags ?? null;
        if (! $tags || $tags->isEmpty()) return null;
        
        return $tags->pluck('name')->all();
    }

    /**
     * Get instructor name safely from course
     */
    private function getInstructorName($course): string
    {
        if (!$course) {
            return 'Instructor';
        }
        
        // Check if instructor relation is loaded
        if ($course->relationLoaded('instructor') && $course->instructor) {
            return $course->instructor->full_name;
        }
        
        // Try to load the relationship
        $course->loadMissing('instructor');
        
        return $course->instructor?->full_name ?? 'Instructor';
    }
}