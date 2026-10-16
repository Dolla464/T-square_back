<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendance_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('token_suffix', 4);
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('issued_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('revoked_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['student_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendance_cards');
    }
};
