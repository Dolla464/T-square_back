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
        // 1. Modify the questions table
        Schema::table('questions', function (Blueprint $table) {
            // Add a compound index for faster fetching of active exam questions
            $table->index(['exam_id', 'deleted_at']);
        });

        // 2. Modify the choices table
        Schema::table('choices', function (Blueprint $table) {
            // Convert data type from TEXT to VARCHAR(255) for faster performance
            $table->string('choice_text', 255)->change();
            
            // Add Soft Deletes feature
            $table->softDeletes();

            // Add a compound index for fetching correct answers for a question at a glance
            $table->index(['question_id', 'is_correct']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the indexes in the questions table
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['exam_id', 'deleted_at']);
        });

        // Revert the modifications in the choices table
        Schema::table('choices', function (Blueprint $table) {
            $table->dropIndex(['question_id', 'is_correct']);
            $table->dropSoftDeletes();
            $table->text('choice_text')->change(); 
        });
    }
};