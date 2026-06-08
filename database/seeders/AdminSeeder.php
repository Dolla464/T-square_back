<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * AdminSeeder
 * -----------
 * ينشئ مساعدي المدير (sub-admins).
 * المدير الرئيسي يُنشأ في AdminUserSeeder.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $subAdmins = [
            ['name' => 'Operations Manager', 'email' => 'ops@tsquare.com'],
            ['name' => 'Content Manager',    'email' => 'content@tsquare.com'],
        ];

        foreach ($subAdmins as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles(['admin']);

            Admin::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $data['name'],
                    'phone'     => '012' . rand(10000000, 99999999),
                    'gender'    => 'male',
                    'status'    => 'active',
                ]
            );
        }

        $this->command->info('✓ AdminSeeder: تم إنشاء ' . count($subAdmins) . ' مساعدي مدير.');
    }
}
