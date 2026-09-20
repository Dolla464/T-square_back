<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_attempts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            if (! Schema::hasColumn('exam_attempts', 'ongoing_slot')) {
                DB::statement("ALTER TABLE exam_attempts ADD COLUMN ongoing_slot VARCHAR(50) AS (IF(status = 'ongoing', CONCAT(student_id, '-', exam_id), NULL)) STORED");
                DB::statement('CREATE UNIQUE INDEX exam_attempts_ongoing_slot_unique ON exam_attempts (ongoing_slot)');
            }

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS exam_attempts_unique_ongoing ON exam_attempts (student_id, exam_id) WHERE status = 'ongoing'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS exam_attempts_unique_ongoing ON exam_attempts (student_id, exam_id) WHERE status = 'ongoing'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('exam_attempts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            if (Schema::hasColumn('exam_attempts', 'ongoing_slot')) {
                DB::statement('DROP INDEX exam_attempts_ongoing_slot_unique ON exam_attempts');
                DB::statement('ALTER TABLE exam_attempts DROP COLUMN ongoing_slot');
            }

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS exam_attempts_unique_ongoing');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS exam_attempts_unique_ongoing');
        }
    }
};
