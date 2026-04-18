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
        Schema::create('course_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('instructor_id')->constrained('instructors')->onDelete('cascade');

            // التقييمات (دقة 3,2 تعني رقم زي 4.50)
            $table->decimal('content_rating', 3, 2)->comment('تقييم المحتوى');
            $table->decimal('instructor_rating', 3, 2)->comment('تقييم المدرب');
            $table->decimal('center_rating', 3, 2)->comment('تقييم المركز والخدمات');
            $table->decimal('rating', 3, 2)->comment('التقييم الكلي');

            $table->text('overall_comment')->nullable();
            $table->timestamps();

            // الفهارس
            $table->unique(['course_id', 'student_id'], 'one_review_per_student');
            $table->index('instructor_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_reviews');
    }
};
