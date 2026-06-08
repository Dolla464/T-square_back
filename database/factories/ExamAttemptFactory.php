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
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 month', 'now');

        return [
            'student_id'  => Student::inRandomOrder()->first()?->id ?? Student::factory(),
            'exam_id'     => Exam::inRandomOrder()->first()?->id ?? Exam::factory(),
            'status'      => 'completed',
            'started_at'  => $start,
            'finished_at' => (clone $start)->modify('+' . rand(20, 60) . ' minutes'),
            'score'       => $this->faker->randomFloat(2, 0, 100),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status'      => 'in_progress',
            'finished_at' => null,
            'score'       => null,
        ]);
    }
}
