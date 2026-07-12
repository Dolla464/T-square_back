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

        DB::statement("ALTER TABLE users MODIFY role ENUM('student','instructor','admin','receptionist') DEFAULT 'student'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Remove receptionist users first to avoid data truncation
        DB::table('users')->where('role', 'receptionist')->update(['role' => 'student']);
        DB::statement("ALTER TABLE users MODIFY role ENUM('student','instructor','admin') DEFAULT 'student'");
    }
};
