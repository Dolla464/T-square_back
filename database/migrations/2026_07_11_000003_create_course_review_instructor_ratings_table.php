<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_review_instructor_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_review_id')->constrained('course_reviews')->cascadeOnDelete();
            $table->foreignId('course_instructor_id')->constrained('course_instructor')->restrictOnDelete();
            $table->decimal('instructor_rating', 3, 2);
            $table->timestamps();

            $table->unique(['course_review_id', 'course_instructor_id'], 'review_course_instructor_unique');
            $table->index('course_instructor_id');
        });

        DB::table('course_reviews')
            ->whereNotNull('instructor_id')
            ->orderBy('id')
            ->each(function ($review) {
                $pivotId = DB::table('course_instructor')
                    ->where('course_id', $review->course_id)
                    ->where('instructor_id', $review->instructor_id)
                    ->value('id');

                if ($pivotId && $review->instructor_rating !== null) {
                    DB::table('course_review_instructor_ratings')->insert([
                        'course_review_id' => $review->id,
                        'course_instructor_id' => $pivotId,
                        'instructor_rating' => $review->instructor_rating,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_review_instructor_ratings');
    }
};
