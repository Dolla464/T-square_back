<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLearning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLearning>
 */
class CourseLearningFactory extends Factory
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
            'title' => $this->faker->sentence(6), // جملة تعبر عن مهارة سيتعلمها
        ];
    }
}
