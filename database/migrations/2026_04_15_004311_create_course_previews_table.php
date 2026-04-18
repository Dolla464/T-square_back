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
        Schema::create('course_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->string('title', 255);
            $table->string('video_url', 500);
            $table->text('description')->nullable();
            $table->enum('video_provider', ['youtube', 'vimeo', 'upload', 'external'])->default('youtube');
            $table->integer('duration_seconds')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // الفهرسة للبحث السريع عن فيديوهات كورس معين
            $table->index('course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_previews');
    }
};
