<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // 1. امسح الـ unique index القديم
            $table->dropUnique(['student_qr_code']);
            
            // 2. غيّر الـ column لـ nullable
            $table->string('student_qr_code')->nullable()->change();
            
            // 3. أضف الـ unique index تاني (بس على القيم المش null)
            // MySQL 8.0.13+ بيدعم: unique + nullable
            $table->unique('student_qr_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropUnique(['student_qr_code']);
            $table->string('student_qr_code')->unique()->change();
        });
    }
};
