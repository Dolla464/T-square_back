<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseReview>
 */
class CourseReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cRating = $this->faker->randomFloat(2, 1, 5);
        $iRating = $this->faker->randomFloat(2, 1, 5);
        $ceRating = $this->faker->randomFloat(2, 1, 5);

        return [
            'course_id' => Course::factory(),
            'student_id' => Student::factory(),
            'instructor_id' => Instructor::factory(),
            'content_rating' => $cRating,
            'instructor_rating' => $iRating,
            'center_rating' => $ceRating,
            'rating' => ($cRating + $iRating + $ceRating) / 3, // حساب المتوسط
            'overall_comment' => $this->faker->sentence(15),
        ];
    }
}
