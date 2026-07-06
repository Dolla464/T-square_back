<?php

namespace App\Console\Commands;

use Database\Seeders\AdminUserSeeder;
use Database\Seeders\ReceptionistSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TruncateAllTables extends Command
{
    protected $signature = 'db:truncate-all
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Truncate all database tables and re-seed essential data (roles, system accounts, settings)';

    /** Tables that must never be wiped. */
    private const PROTECTED_TABLES = ['migrations'];

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->newLine();
            $this->components->warn('This will DELETE ALL DATA from every table in the database.');
            $this->line('  Only essential data (roles, system accounts, settings) will be restored afterwards.');
            $this->newLine();

            if (! $this->confirm('Are you sure you want to continue?')) {
                $this->components->info('Aborted. No changes were made.');
                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->components->info('Starting database truncation…');

        // ── 1. Collect tables ────────────────────────────────────────────────
        $tables = $this->getAllTables();

        if (empty($tables)) {
            $this->components->warn('No tables found in the database.');
            return self::SUCCESS;
        }

        // ── 2. Truncate ──────────────────────────────────────────────────────
        $truncated = $this->truncateTables($tables);

        $this->components->twoColumnDetail(
            '<fg=green>Tables truncated</>',
            (string) $truncated
        );

        // ── 3. Re-seed essential data ────────────────────────────────────────
        $this->newLine();
        $this->components->info('Re-seeding essential data…');

        $this->call('db:seed', ['--class' => RoleSeeder::class,        '--force' => true]);
        $this->call('db:seed', ['--class' => AdminUserSeeder::class,   '--force' => true]);
        $this->call('db:seed', ['--class' => ReceptionistSeeder::class,'--force' => true]);
        $this->call('db:seed', ['--class' => SettingSeeder::class,     '--force' => true]);

        // ── 4. Summary ───────────────────────────────────────────────────────
        $this->newLine();
        $this->components->info('Done! Essential data has been restored.');
        $this->newLine();

        $this->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',        'admin@tsquare.com',        'Admin@12345'],
                ['Instructor',   'instructor@tsquare.com',   'Instructor@12345'],
                ['Student',      'student@tsquare.com',      'Student@12345'],
                ['Receptionist', 'receptionist@tsquare.com', 'Receptionist@12345'],
            ]
        );

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Return all table names in the current database, excluding protected ones.
     *
     * @return string[]
     */
    private function getAllTables(): array
    {
        $driver = DB::getDriverName();

        $tables = match ($driver) {
            'mysql', 'mariadb' => $this->getMysqlTables(),
            'pgsql'            => $this->getPgsqlTables(),
            'sqlite'           => $this->getSqliteTables(),
            default            => $this->getMysqlTables(),
        };

        return array_values(
            array_filter($tables, fn (string $t) => ! in_array($t, self::PROTECTED_TABLES, true))
        );
    }

    /** @return string[] */
    private function getMysqlTables(): array
    {
        return array_map(
            fn ($row) => array_values((array) $row)[0],
            DB::select('SHOW TABLES')
        );
    }

    /** @return string[] */
    private function getPgsqlTables(): array
    {
        return array_column(
            DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"),
            'tablename'
        );
    }

    /** @return string[] */
    private function getSqliteTables(): array
    {
        return array_column(
            DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"),
            'name'
        );
    }

    /**
     * Disable FK checks, truncate every table, then re-enable FK checks.
     * Returns the count of tables that were actually truncated.
     */
    private function truncateTables(array $tables): int
    {
        $driver    = DB::getDriverName();
        $truncated = 0;

        try {
            $this->disableForeignKeyChecks($driver);

            foreach ($tables as $table) {
                DB::table($table)->truncate();
                $this->components->twoColumnDetail("  Truncated <fg=yellow>{$table}</>", '<fg=green>✓</>');
                $truncated++;
            }
        } finally {
            $this->enableForeignKeyChecks($driver);
        }

        return $truncated;
    }

    private function disableForeignKeyChecks(string $driver): void
    {
        match ($driver) {
            'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS=0'),
            'pgsql'            => DB::statement('SET session_replication_role = replica'),
            'sqlite'           => DB::statement('PRAGMA foreign_keys = OFF'),
            default            => null,
        };
    }

    private function enableForeignKeyChecks(string $driver): void
    {
        match ($driver) {
            'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS=1'),
            'pgsql'            => DB::statement('SET session_replication_role = DEFAULT'),
            'sqlite'           => DB::statement('PRAGMA foreign_keys = ON'),
            default            => null,
        };
    }
}
