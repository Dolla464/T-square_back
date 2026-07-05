<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ReceptionistSeeder
 * ------------------
 * ينشئ حساب موظف الاستقبال الافتراضي ويُعيّن دور receptionist في Spatie.
 * يستخدم updateOrCreate لتجنب التكرار عند إعادة التشغيل.
 * يُزامن عمود users.role وجداول Spatie معاً.
 */
class ReceptionistSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'receptionist@tsquare.com'],
            [
                'name'              => 'T-Square Receptionist',
                'password'          => Hash::make('Receptionist@12345'),
                'role'              => 'receptionist',
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles(['receptionist']);

        // مزامنة عمود users.role إذا لم يُضبط تلقائياً (حالة المستخدمين القدامى)
        if ($user->getRawOriginal('role') !== 'receptionist') {
            $user->updateQuietly(['role' => 'receptionist']);
        }

        $this->command->info('✓ ReceptionistSeeder: تم إنشاء حساب موظف الاستقبال.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [['Receptionist', 'receptionist@tsquare.com', 'Receptionist@12345']]
        );
    }
}
