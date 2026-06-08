<?php

namespace Database\Seeders;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * InstructorSeeder
 * ----------------
 * ينشئ مستخدمين من نوع instructor، يُعيّن لهم دور Spatie،
 * وينشئ ملف Instructor مرتبط بكل منهم.
 */
class InstructorSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            'Web Development',
            'Mobile Development',
            'UI/UX Design',
            'Data Science',
            'Cybersecurity',
            'DevOps',
            'Digital Marketing',
        ];

        for ($i = 1; $i <= 7; $i++) {
            $user = User::factory()->instructor()->create([
                'name'     => "Instructor {$i}",
                'email'    => "instructor{$i}@tsquare.com",
                'password' => Hash::make('password'),
            ]);

            $user->syncRoles(['instructor']);

            Instructor::create([
                'user_id'      => $user->id,
                'full_name'    => $user->name,
                'phone'        => '010' . str_pad($i * 11, 8, '0', STR_PAD_LEFT),
                'gender'       => $i % 2 === 0 ? 'female' : 'male',
                'field'        => $fields[$i - 1],
                'bio'          => "مدرب متخصص في {$fields[$i - 1]} مع خبرة تزيد عن " . rand(3, 10) . " سنوات.",
                'insta_url'    => "https://instagram.com/instructor{$i}",
                'linkedin_url' => "https://linkedin.com/in/instructor{$i}",
                'facebook_url' => "https://facebook.com/instructor{$i}",
                'status'       => 'active',
            ]);
        }

        $this->command->info('✓ InstructorSeeder: تم إنشاء 7 مدربين بنجاح.');
    }
}
