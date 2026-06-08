<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * StudentSeeder
 * -------------
 * ينشئ مستخدمين من نوع student، يُعيّن لهم دور Spatie،
 * وينشئ ملف Student مرتبط بكل منهم.
 */
class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $count = 30;

        for ($i = 1; $i <= $count; $i++) {
            $user = User::factory()->student()->create([
                'password' => Hash::make('password'),
            ]);

            $user->syncRoles(['student']);

            Student::create([
                'user_id'           => $user->id,
                'full_name'         => $user->name,
                'phone'             => '011' . str_pad($i * 7, 8, '0', STR_PAD_LEFT),
                'enrollment_number' => 'STU-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'gender'            => $i % 3 === 0 ? 'female' : 'male',
                'status'            => 'active',
            ]);
        }

        $this->command->info("✓ StudentSeeder: تم إنشاء {$count} طالباً بنجاح.");
    }
}
