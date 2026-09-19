<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::inRandomOrder()->first()->id ?? Exam::factory(),
            'type' => Question::TYPE_MCQ,
            'question_text' => $this->faker->sentence(10).'?',
            'marks' => $this->faker->randomElement([5, 10, 15]),
        ];
    }

    public function essay(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Question::TYPE_ESSAY,
        ]);
    }

    public function mcq(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Question::TYPE_MCQ,
        ]);
    }
}
