<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseInstructor;
use App\Models\Instructor;
use App\Models\LearningGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningGroup>
 */
class LearningGroupFactory extends Factory
{
    public function definition(): array
    {
        $course = Course::inRandomOrder()->first() ?? Course::factory()->create();
        $courseInstructor = CourseInstructor::query()
            ->where('course_id', $course->id)
            ->first();

        if (! $courseInstructor) {
            $instructor = Instructor::inRandomOrder()->first() ?? Instructor::factory()->create();
            $courseInstructor = CourseInstructor::create([
                'course_id' => $course->id,
                'instructor_id' => $instructor->id,
                'sort_order' => 0,
            ]);
        }

        return [
            'group_name' => 'Batch #'.$this->faker->numberBetween(1, 10),
            'course_id' => $course->id,
            'course_instructor_id' => $courseInstructor->id,
        ];
    }
}
