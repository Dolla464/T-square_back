<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
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
            'avatar' => 'admin_default.png',
            'gender' => $this->faker->randomElement(['male', 'female']),
            'status' => 'active',
        ];
    }
}
