<?php

namespace App\Services\User;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * download blob itself (consistent with the inline preview path).
     */
    public function downloadFile(Certificate $certificate): StreamedResponse
    {
        return $this->streamCertificateFile($certificate->certificate_url);
    }

    /**
     * Shared file streamer that hides the file's true nature from download
     * managers (IDM) so the browser can read the bytes for both inline
     * preview and client-side download.
     */
    private function streamCertificateFile(?string $path): StreamedResponse
    {
        if (empty($path) || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Certificate file not found on server.');
        }

        return Storage::disk('public')->response($path, null, [
            // Trick download managers into treating the payload as plain text.
            'Content-Type' => 'text/plain',
            'X-Download-Options' => 'noopen',
            'Content-Disposition' => 'inline',
            // CORS headers so the SPA can read the bytes smoothly.
            'Access-Control-Allow-Origin' => config('app.frontend_url'),
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Expose-Headers' => 'Content-Disposition, Content-Length',
        ]);
    }

    public function issueCertificate(Enrollment $enrollment, bool $force = false)
    {
        // Ensure instructor relation on course is loaded to avoid N+1 when extracting instructor name
        $enrollment->loadMissing(['student', 'course.instructor']);

        // 1. Check if the certificate already exists to prevent duplicates
        // Note: removed incorrect extra arguments to where() calls
        $existingCert = Certificate::query()
            ->where('student_id', $enrollment->student_id)
            ->where('course_id', $enrollment->course_id)
            ->first();

        if ($existingCert && ! $force) {
            return $existingCert;
        }

        // If forcing regeneration, delete old file and DB record so a fresh certificate is created
        if ($existingCert && $force) {
            if (! empty($existingCert->certificate_url) && Storage::disk('public')->exists($existingCert->certificate_url)) {
                Storage::disk('public')->delete($existingCert->certificate_url);
            }
            // delete the old record to allow creating a new one with fresh metadata
            $existingCert->delete();
        }

        // 2. Define a unique file path and save the PDF to storage
        $fileName = 'certificates/cert_' . Str::random(16) . '.pdf';
        $this->certificatePdfData([
            'name' => $enrollment->student->full_name,
            'course' => $enrollment->course->title,
            'date' => now()->format('Y-m-d'),
            'tags' => $this->extractCourseTags($enrollment->course),
            'instructor_name' => $enrollment->course->instructor->full_name ?? null,
        ])->disk('public')->save($fileName);

        // 3. Generate a unique Certificate Number
        $certificateNum = 'TSQ-' . date('Y') . '-' . strtoupper(Str::random(8));

        // 4. Save to the Database
        $certificate = Certificate::create([
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'certificate_url' => $fileName,
            'certificate_num' => $certificateNum,
            'issued_at' => now(),
        ]);

        return $certificate;
    }

    public function generateLiveCertificate(Enrollment $enrollment)
    {
        $enrollment->loadMissing(['student', 'course.instructor']);

        return $this->certificatePdfData([
            'name' => $enrollment->student->full_name,
            'course' => $enrollment->course->title,
            'date' => now()->format('Y-m-d'),
            'tags' => $this->extractCourseTags($enrollment->course),
            'instructor_name' => $enrollment->course->instructor->full_name ?? null,
        ])
            ->name('certificate-' . $enrollment->id . '.pdf');
    }

    /**
     * @param  ExamAttempt  $attempt
     */
    public function generateBinaryPdf($attempt): string
    {
        $attempt->loadMissing(['student', 'exam.course.instructor']);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'certificate_') . '.pdf';

        $this->certificatePdfData([
            'name' => $attempt->student->full_name,
            'course' => $attempt->exam->course->title,
            'date' => now()->format('Y-m-d'),
            'tags' => $this->extractCourseTags($attempt->exam->course),
            'instructor_name' => $attempt->exam->course->instructor->full_name ?? null,
        ])->save($temporaryPath);

        $pdfContent = file_get_contents($temporaryPath);
        @unlink($temporaryPath);

        return $pdfContent;
    }

    /**
     * Generate a PDF instance for a certificate.
     *
     * @param  array{name: string, course: string, date: string}  $data
     * @return PdfBuilder
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    private function certificatePdfData(array $data)
    {
        // Defensive: Ensure all required keys exist and are strings
        foreach (['name', 'course', 'date'] as $key) {
            if (! array_key_exists($key, $data) || ! is_string($data[$key]) || empty($data[$key])) {
                throw new \InvalidArgumentException("Certificate PDF data missing or invalid for key: {$key}");
            }
        }

        // Ensure tags key always exists to keep the view simple
        if (! array_key_exists('tags', $data)) {
            $data['tags'] = null;
        }

        // Encapsulate Browsershot configuration
        $browsershotConfigurator = function ($browsershot) {
            $nodeBinary = config('services.browsershot.node_binary', 'C:\Program Files\nodejs\node.exe');
            $npmBinary = config('services.browsershot.npm_binary', 'C:\Program Files\nodejs\npm.cmd');
            $nodeModulesPath = config('services.browsershot.include_path', base_path('node_modules'));

            if (! file_exists($nodeBinary) || ! file_exists($npmBinary)) {
                throw new \Exception('Browsershot dependencies missing or misconfigured');
            }

            $browsershot->setNodeBinary($nodeBinary)
                ->setNpmBinary($npmBinary)
                ->setIncludePath($nodeModulesPath);
        };

        try {
            return Pdf::view('certificate', $data)
                ->landscape()
                ->showBackground(true)
                ->withBrowsershot($browsershotConfigurator);
        } catch (\Throwable $e) {
            // Optionally, consider logging here for better observability
            throw new \Exception('Failed to generate certificate PDF: ' . $e->getMessage(), 0, $e);
        }
    }

    // public function sendWhatsAppCertificate($phone, $message)
    // {
    //     $instanceId = env('ULTRAMSG_INSTANCE_ID');
    //     $token = env('ULTRAMSG_TOKEN');
    //
    //     return Http::post("https://api.ultramsg.com/{$instanceId}/messages/chat", [
    //         'token' => $token,
    //         'to' => $phone,
    //         'body' => $message,
    //     ]);
    // }

    /**
     * Extract tags from a Course model in a normalized form.
     * Returns an array of tag names, a single string, or null when none.
     *
     * @param  mixed  $course
     * @return array|string|null
     */
    private function extractCourseTags($course)
    {
        if (! $course) {
            return null;
        }

        // If relation isn't loaded, attempt to load safely
        if (! $course->relationLoaded('tags')) {
            $course->load('tags');
        }

        $tags = $course->tags ?? null;

        if (! $tags || $tags->isEmpty()) {
            return null;
        }

        // Return array of names
        return $tags->pluck('name')->all();
    }
}
