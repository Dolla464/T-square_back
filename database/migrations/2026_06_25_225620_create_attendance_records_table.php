<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('student_qr_code')->unique();
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('absent');
            $table->enum('marked_by', ['student_qr', 'instructor_manual', 'system'])->default('system');
            $table->timestamp('marked_at')->nullable();
            $table->timestamp('qr_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
