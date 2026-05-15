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
        // Add Soft Deletes to the Learnings table
        Schema::table('course_learnings', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add Soft Deletes to the Previews table
        Schema::table('course_previews', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_learnings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('course_previews', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

