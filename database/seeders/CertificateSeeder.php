<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * CertificateSeeder
 * -----------------
 * يُصدر شهادات فقط للاشتراكات المكتملة (is_completed = true).
 * يستخدم updateOrCreate لمنع التكرار.
 */
class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        $completedEnrollments = Enrollment::where('is_completed', true)->get();

        if ($completedEnrollments->isEmpty()) {
            $this->command->warn('لا توجد اشتراكات مكتملة — تأكد أن EnrollmentSeeder يضع is_completed = true لبعضها.');
            return;
        }

        $count = 0;

        foreach ($completedEnrollments as $enrollment) {
            Certificate::updateOrCreate(
                [
                    'student_id' => $enrollment->student_id,
                    'course_id'  => $enrollment->course_id,
                ],
                [
                    'certificate_url' => 'certificates/CERT_' . Str::upper(Str::random(10)) . '.pdf',
                    'certificate_num' => 'TSQ-' . date('Y') . '-' . strtoupper(Str::random(8)),
                    'issued_at'       => $enrollment->completed_at ?? now(),
                    'status'          => 'issued',
                ]
            );

            $count++;
        }

        $this->command->info("✓ CertificateSeeder: تم إصدار {$count} شهادة للطلاب المكتملين.");
    }
}
