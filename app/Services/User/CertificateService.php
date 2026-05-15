<?php

namespace App\Services\User;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class CertificateService
{
    public function issueCertificate(Enrollment $enrollment)
    {
        $enrollment->loadMissing(['student', 'course']);

        // 1. Check if the certificate already exists to prevent duplicates
        $existingCert = Certificate::where('student_id', '=', $enrollment->student_id, 'and')
            ->where('course_id', '=', $enrollment->course_id, 'and')
            ->first();

        if ($existingCert) {
            return $existingCert;
        }

        // 2. Define a unique file path and save the PDF to storage
        $fileName = 'certificates/cert_'.Str::random(16).'.pdf';
        $this->certificatePdfData([
            'name' => $enrollment->student->full_name,
            'course' => $enrollment->course->title,
            'date' => now()->format('Y-m-d'),
        ])->disk('public')->save($fileName);

        // 3. Generate a unique Certificate Number
        $certificateNum = 'TSQ-'.date('Y').'-'.strtoupper(Str::random(8));

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
        $enrollment->loadMissing(['student', 'course']);

        return $this->certificatePdfData([
            'name' => $enrollment->student->full_name,
            'course' => $enrollment->course->title,
            'date' => now()->format('Y-m-d'),
        ])
            ->name('certificate-'.$enrollment->id.'.pdf');
    }

    /**
     * @param  ExamAttempt  $attempt
     */
    public function generateBinaryPdf($attempt): string
    {
        $attempt->loadMissing(['student', 'exam.course']);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'certificate_').'.pdf';

        $this->certificatePdfData([
            'name' => $attempt->student->full_name,
            'course' => $attempt->exam->course->title,
            'date' => now()->format('Y-m-d'),
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
                ->withBrowsershot($browsershotConfigurator);
        } catch (\Throwable $e) {
            // Optionally, consider logging here for better observability
            throw new \Exception('Failed to generate certificate PDF: '.$e->getMessage(), 0, $e);
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
}
