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
        Schema::create('learning_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_name', 255);

            // الربط مع الكورسات والمدربين
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('instructor_id')->constrained('instructors')->onDelete('cascade');

            $table->timestamps();

            // الفهرسة لتحسين الأداء في البحث عن مجموعات مدرب معين
            $table->index('instructor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_groups');
    }
};
