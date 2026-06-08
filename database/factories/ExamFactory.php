<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    public function definition(): array
    {
        $totalMarks = $this->faker->randomElement([50, 100, 200]);

        return [
            'course_id'            => Course::inRandomOrder()->first()?->id ?? Course::factory(),
            'title'                => $this->faker->words(3, true) . ' Exam',
            'description'          => $this->faker->paragraph(),
            'duration'             => $this->faker->randomElement([30, 60, 90, 120]),
            'total_marks'          => $totalMarks,
            'passing_mark'         => round($totalMarks * 0.6, 2),
            'is_active'            => true,
            'is_final'             => false,
            'max_attempts'         => $this->faker->randomElement([1, 2, 3]),
            'questions_per_attempt'=> $this->faker->numberBetween(5, 15),
            'shuffle_questions'    => $this->faker->boolean(50),
        ];
    }

    public function final(): static
    {
        return $this->state(fn () => ['is_final' => true, 'is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
