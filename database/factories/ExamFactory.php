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
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::inRandomOrder()->first()->id ?? Course::factory(),
            'title' => $this->faker->words(3, true) . ' Exam',
            'description' => $this->faker->paragraph(),
            'duration' => $this->faker->randomElement([30, 60, 90, 120]), // بالدقائق
            'total_marks' => $this->faker->randomElement([50, 100, 200]),
            'is_active' => true,
        ];
    }
}
