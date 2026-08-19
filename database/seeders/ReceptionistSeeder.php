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
        if (app()->environment('production') && ! env('ALLOW_DEMO_SEEDERS')) {
            $this->command?->warn('ReceptionistSeeder skipped in production. Set ALLOW_DEMO_SEEDERS=true to override.');

            return;
        }

        $password = env('SEED_RECEPTIONIST_PASSWORD', 'Receptionist@12345');

        $user = User::updateOrCreate(
            ['email' => 'receptionist@tsquare.com'],
            [
                'name'              => 'T-Square Receptionist',
                'password'          => Hash::make($password),
                'role'              => 'receptionist',
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles(['receptionist']);

        // مزامنة عمود users.role إذا لم يُضبط تلقائياً (حالة المستخدمين القدامى)
        if ($user->getRawOriginal('role') !== 'receptionist') {
            $user->updateQuietly(['role' => 'receptionist']);
        }

        $this->command->info('✓ ReceptionistSeeder: demo receptionist ready (password from SEED_RECEPTIONIST_PASSWORD).');
    }
}
