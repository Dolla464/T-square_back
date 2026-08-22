<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('video_source_type')->nullable();
            $table->string('google_drive_file_id', 255)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('drive_validation_status')->nullable();
            $table->text('drive_validation_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('course_id');
            $table->index(['course_id', 'sort_order']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
