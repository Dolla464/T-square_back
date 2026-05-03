<?php

namespace App\Services\User;

use App\Models\Enrollment;
use Spatie\LaravelPdf\Facades\Pdf;

class CertificateService
{
    public function generateLiveCertificate(Enrollment $enrollment)
    {
        // بننادي الـ Blade وبنبعت له داتا الطالب والكورس
        $enrollment->loadMissing(['student', 'course']);

        return Pdf::view('certificate', [
            'name' => $enrollment->student->full_name,
            'course'  => $enrollment->course->title,
            'date'         => now()->format('Y-m-d'),
        ])
            ->name('certificate-' . $enrollment->id . '.pdf');
    }
}
