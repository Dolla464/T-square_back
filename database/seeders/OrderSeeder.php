<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * OrderSeeder
 * -----------
 * تنبيه: الـ Orders الآن تُنشأ تلقائياً داخل EnrollmentSeeder
 * لضمان ربط كل Order بـ Enrollment بشكل صحيح.
 *
 * هذا الـ Seeder محتفظ به فقط للتوافق ولا يُستدعى من DatabaseSeeder.
 * إذا احتجت orders إضافية لاحقاً، يمكن تفعيله هنا.
 */
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('ℹ OrderSeeder: الـ Orders تُنشأ من داخل EnrollmentSeeder — لا حاجة لتشغيل هذا بشكل مستقل.');
    }
}
