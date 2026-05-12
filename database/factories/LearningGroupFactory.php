<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\LearningGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningGroup>
 */
class LearningGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_name' => 'Batch #'.$this->faker->numberBetween(1, 10),
            'course_id' => Course::inRandomOrder()->first()->id ?? Course::factory(),
            'instructor_id' => Instructor::inRandomOrder()->first()->id ?? Instructor::factory(),
        ];
    }
}
