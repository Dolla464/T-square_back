<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::inRandomOrder()->first()->id ?? Student::factory(),
            'total_amount' => $this->faker->randomFloat(2, 100, 1000),
            'status' => $this->faker->randomElement(['pending', 'completed', 'cancelled']),
            'billing_name' => $this->faker->name(),
            'billing_email' => $this->faker->safeEmail(),
            'billing_phone' => $this->faker->phoneNumber(),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
