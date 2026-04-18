<?php

namespace Database\Factories;

use App\Models\Instructor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instructor>
 */
class InstructorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(), // سينشئ مستخدم جديد لكل مدرب
            'full_name' => $this->faker->name(),
            'phone' => $this->faker->unique()->phoneNumber(),
            'avatar' => 'default_avatar.png',
            'bio' => $this->faker->paragraphs(2, true),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'insta_url' => 'https://instagram.com/' . $this->faker->userName(),
            'linkedin_url' => 'https://linkedin.com/in/' . $this->faker->userName(),
            'facebook_url' => 'https://facebook.com/' . $this->faker->userName(),
            'status' => 'active',
        ];
    }
}
