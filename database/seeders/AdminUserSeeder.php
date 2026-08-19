<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * AdminUserSeeder
 * ---------------
 * ينشئ حساب المدير الافتراضي للنظام.
 * يستخدم updateOrCreate لتجنب التكرار عند إعادة التشغيل.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && ! env('ALLOW_DEMO_SEEDERS')) {
            $this->command?->warn('AdminUserSeeder skipped in production. Set ALLOW_DEMO_SEEDERS=true to override.');

            return;
        }

        $adminPassword = env('SEED_ADMIN_PASSWORD', 'Admin@12345');
        $studentPassword = env('SEED_STUDENT_PASSWORD', 'Student@12345');
        $instructorPassword = env('SEED_INSTRUCTOR_PASSWORD', 'Instructor@12345');

        // ─── المدير الرئيسي ───────────────────────────────────────────────
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@tsquare.com'],
            [
                'name'              => 'T-Square Admin',
                'password'          => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]
        );

        $adminUser->syncRoles(['admin']);

        Admin::updateOrCreate(
            ['user_id' => $adminUser->id],
            [
                'full_name' => 'T-Square Admin',
                'phone'     => '01000000001',
                'gender'    => 'male',
                'status'    => 'active',
            ]
        );

        // ─── حساب طالب تجريبي ─────────────────────────────────────────────
        $studentUser = User::updateOrCreate(
            ['email' => 'student@tsquare.com'],
            [
                'name'              => 'Test Student',
                'password'          => Hash::make($studentPassword),
                'email_verified_at' => now(),
            ]
        );

        $studentUser->syncRoles(['student']);

        \App\Models\Student::updateOrCreate(
            ['user_id' => $studentUser->id],
            [
                'full_name'         => 'Test Student',
                'phone'             => '01000000002',
                'enrollment_number' => 'STU-00001',
                'gender'            => 'male',
                'status'            => 'active',
            ]
        );

        // ─── حساب مدرب تجريبي ─────────────────────────────────────────────
        $instructorUser = User::updateOrCreate(
            ['email' => 'instructor@tsquare.com'],
            [
                'name'              => 'Test Instructor',
                'password'          => Hash::make($instructorPassword),
                'email_verified_at' => now(),
            ]
        );

        $instructorUser->syncRoles(['instructor']);

        \App\Models\Instructor::updateOrCreate(
            ['user_id' => $instructorUser->id],
            [
                'full_name' => 'Test Instructor',
                'phone'     => '01000000003',
                'gender'    => 'male',
                'field'     => 'Software Engineering',
                'bio'       => 'مدرب متخصص في علوم الحاسب وتطوير البرمجيات.',
                'status'    => 'active',
            ]
        );

        $this->command->info('✓ AdminUserSeeder: demo accounts ready (passwords from SEED_* env vars in non-production).');
    }
}
