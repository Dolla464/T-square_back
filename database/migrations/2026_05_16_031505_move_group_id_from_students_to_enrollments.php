<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move group_id from the students table to the enrollments table so that
     * a student can belong to different learning groups across different courses.
     */
    public function up(): void
    {
        // ── 1. Add group_id to enrollments ───────────────────────────────────
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('order_id');

            $table->foreign('group_id')
                ->references('id')
                ->on('learning_groups')
                ->onDelete('set null');

            $table->index('group_id');
        });

        // ── 2. Migrate existing data ──────────────────────────────────────────
        // Each student currently has a single group_id. We copy it to every
        // enrollment row for that student so no data is lost.
        // Correlated-subquery form works on both MySQL and SQLite (tests).
        DB::statement('
            UPDATE enrollments
            SET group_id = (
                SELECT s.group_id
                FROM students AS s
                WHERE s.id = enrollments.student_id
                AND s.group_id IS NOT NULL
            )
            WHERE enrollments.student_id IN (
                SELECT id FROM students WHERE group_id IS NOT NULL
            )
        ');

        // ── 3. Remove group_id from students ──────────────────────────────────
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
        });
    }

    /**
     * Reverse the migration: restore group_id on students (uses the first
     * enrollment group found for each student as the best approximation).
     */
    public function down(): void
    {
        // ── 1. Restore group_id column on students ────────────────────────────
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('enrollment_number');

            $table->foreign('group_id')
                ->references('id')
                ->on('learning_groups')
                ->onDelete('set null');

            $table->index('group_id');
        });

        // ── 2. Restore data (best-effort: pick the earliest enrollment's group) ─
        // Correlated-subquery form works on both MySQL and SQLite (tests).
        DB::statement('
            UPDATE students
            SET group_id = (
                SELECT e.group_id
                FROM enrollments AS e
                WHERE e.student_id = students.id
                AND e.group_id IS NOT NULL
                ORDER BY e.created_at ASC
                LIMIT 1
            )
            WHERE id IN (
                SELECT student_id FROM enrollments WHERE group_id IS NOT NULL
            )
        ');

        // ── 3. Remove group_id from enrollments ───────────────────────────────
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
