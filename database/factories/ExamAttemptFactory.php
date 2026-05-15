<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 month', 'now');

        return [
            'student_id' => Student::inRandomOrder()->first()->id ?? Student::factory(),
            'exam_id' => Exam::inRandomOrder()->first()->id ?? Exam::factory(),
            'started_at' => $start,
            'finished_at' => (clone $start)->modify('+'.rand(20, 60).' minutes'),
            'score' => $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
