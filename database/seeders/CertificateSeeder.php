<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CertificateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // هنجيب فقط الاشتراكات اللي حالتها "مكتملة" (is_completed)
        $completedEnrollments = Enrollment::where('is_completed', true)->get();

        if ($completedEnrollments->isEmpty()) {
            $this->command->info('مفيش طلاب خلصوا كورسات لسه ، شغل الـ EnrollmentSeeder بحالات مكتملة الأول!');

            return;
        }

        foreach ($completedEnrollments as $enrollment) {
            Certificate::create([
                'student_id' => $enrollment->student_id,
                'course_id' => $enrollment->course_id,
                'certificate_url' => 'certificates/CERT_'.Str::random(10).'.pdf',
                'certificate_num' => 'TSQ-'.date('Y').'-'.strtoupper(Str::random(8)),
                'issued_at' => $enrollment->completed_at ?? now(),
            ]);
        }

        $this->command->info('تم إصدار شهادات لكل الطلاب الناجحين بنجاح!');
    }
}
