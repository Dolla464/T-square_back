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
        // ─── المدير الرئيسي ───────────────────────────────────────────────
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@tsquare.com'],
            [
                'name'              => 'T-Square Admin',
                'password'          => Hash::make('Admin@12345'),
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
                'password'          => Hash::make('Student@12345'),
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
                'password'          => Hash::make('Instructor@12345'),
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

        $this->command->info('✓ AdminUserSeeder: تم إنشاء حسابات النظام الأساسية.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',      'admin@tsquare.com',      'Admin@12345'],
                ['Student',    'student@tsquare.com',    'Student@12345'],
                ['Instructor', 'instructor@tsquare.com', 'Instructor@12345'],
            ]
        );
    }
}
