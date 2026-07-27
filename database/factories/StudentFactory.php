<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => $this->faker->name(),
            'phone' => $this->faker->unique()->phoneNumber(),
            'avatar' => 'default_student.png',
            'gender' => $this->faker->randomElement(['male', 'female']),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Student $student, ?array $attributes = null) {
            $attributes ??= [];

            $student->forceFill([
                'enrollment_number' => $attributes['enrollment_number']
                    ?? 'STU-'.fake()->unique()->numerify('#####'),
                'status' => $attributes['status'] ?? 'active',
                'created_by' => $attributes['created_by'] ?? 'admin',
            ]);
        });
    }
}
