<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE attendance_records MODIFY COLUMN marked_by ENUM('student_qr', 'student_app', 'instructor_manual', 'receptionist_manual', 'admin_manual', 'system') NOT NULL DEFAULT 'system'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE attendance_records MODIFY COLUMN marked_by ENUM('student_qr', 'student_app', 'instructor_manual', 'receptionist_manual', 'system') NOT NULL DEFAULT 'system'");
    }
};
