<?php

namespace Database\Factories;

use App\Models\ContactUs;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactUs>
 */
class ContactUsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->numerify('01#########'),
            'learning_track' => $this->faker->randomElement([
                'Backend',
                'Frontend',
                'Fullstack',
                'Mobile',
                'UI/UX',
                'Data',
            ]),
            'message' => $this->faker->text(300),
        ];
    }
}

