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
            'enrollment_number' => 'STU-'.$this->faker->unique()->numberBetween(10000, 99999),
            'avatar' => 'default_student.png',
            'gender' => $this->faker->randomElement(['male', 'female']),
            'status' => 'active',
        ];
    }
}
