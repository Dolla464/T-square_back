<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RotateDemoAccountPasswords extends Command
{
    private const DEMO_ACCOUNTS = [
        'admin@tsquare.com' => 'SEED_ADMIN_PASSWORD',
        'instructor@tsquare.com' => 'SEED_INSTRUCTOR_PASSWORD',
        'student@tsquare.com' => 'SEED_STUDENT_PASSWORD',
        'receptionist@tsquare.com' => 'SEED_RECEPTIONIST_PASSWORD',
    ];

    protected $signature = 'demo:rotate-passwords
                            {--force : Skip confirmation prompt}';

    protected $description = 'Rotate passwords for the four fixed demo accounts using SEED_* env variables';

    public function handle(): int
    {
        $missingEnvKeys = $this->missingEnvKeys();

        if ($missingEnvKeys !== []) {
            $this->components->error('Missing or empty environment variables:');
            foreach ($missingEnvKeys as $envKey) {
                $this->line("  - {$envKey}");
            }

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->line('The following demo accounts will be updated:');
            foreach (array_keys(self::DEMO_ACCOUNTS) as $email) {
                $this->line("  - {$email}");
            }
            $this->newLine();

            if (! $this->confirm('Are you sure you want to rotate demo account passwords?')) {
                $this->components->info('Aborted. No passwords were changed.');

                return self::FAILURE;
            }
        }

        $rows = [];
        $updatedCount = 0;

        foreach (self::DEMO_ACCOUNTS as $email => $envKey) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $this->components->warn("User not found: {$email}");
                $rows[] = [$email, 'not found'];

                continue;
            }

            $user->update(['password' => env($envKey)]);
            $updatedCount++;
            $rows[] = [$email, 'updated'];
        }

        $this->newLine();
        $this->table(['Email', 'Status'], $rows);

        if ($updatedCount === 0) {
            $this->components->error('No demo accounts were updated.');

            return self::FAILURE;
        }

        $this->components->info("Updated {$updatedCount} demo account password(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function missingEnvKeys(): array
    {
        $missing = [];

        foreach (self::DEMO_ACCOUNTS as $envKey) {
            if (! filled(env($envKey))) {
                $missing[] = $envKey;
            }
        }

        return $missing;
    }
}
