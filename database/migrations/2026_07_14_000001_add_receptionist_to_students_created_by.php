<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE students MODIFY created_by ENUM('admin', 'site', 'receptionist') NOT NULL DEFAULT 'admin'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("UPDATE students SET created_by = 'admin' WHERE created_by = 'receptionist'");
        DB::statement("ALTER TABLE students MODIFY created_by ENUM('admin', 'site') NOT NULL DEFAULT 'admin'");
    }
};
