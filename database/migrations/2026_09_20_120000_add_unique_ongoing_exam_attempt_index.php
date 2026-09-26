<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ONGOING_SLOT_INDEX = 'exam_attempts_ongoing_slot_unique';

    public function up(): void
    {
        if (! Schema::hasTable('exam_attempts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            if (! Schema::hasColumn('exam_attempts', 'ongoing_slot')) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');

                try {
                    DB::statement("ALTER TABLE exam_attempts ADD COLUMN ongoing_slot VARCHAR(50) AS (IF(`status` = 'ongoing', CONCAT(`student_id`, '-', `exam_id`), NULL)) VIRTUAL");
                } finally {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                }
            }

            if (! $this->ongoingSlotIndexExists()) {
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
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                if ($this->ongoingSlotIndexExists()) {
                    DB::statement('DROP INDEX exam_attempts_ongoing_slot_unique ON exam_attempts');
                }

                if (Schema::hasColumn('exam_attempts', 'ongoing_slot')) {
                    DB::statement('ALTER TABLE exam_attempts DROP COLUMN ongoing_slot');
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
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

    private function ongoingSlotIndexExists(): bool
    {
        $indexes = DB::select(
            'SHOW INDEX FROM exam_attempts WHERE Key_name = ?',
            [self::ONGOING_SLOT_INDEX],
        );

        return $indexes !== [];
    }
};
