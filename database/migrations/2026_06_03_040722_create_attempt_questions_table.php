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
        Schema::create('attempt_questions', function (Blueprint $table) {
            $table->id();
            // Connect the ongoing attempt to the student
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            // Connect the randomly selected question for this attempt
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attempt_questions');
    }
};
