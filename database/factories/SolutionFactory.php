<?php

namespace Database\Factories;

use App\Models\Solution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Solution>
 */
class SolutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4), // عنوان مكون من 4 كلمات
            'description' => $this->faker->paragraphs(3, true), // وصف من 3 فقرات
        ];
    }
}
