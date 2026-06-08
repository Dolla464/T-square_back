<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'               => $this->faker->name(),
            'email'              => $this->faker->unique()->safeEmail(),
            'email_verified_at'  => now(),
            'password'           => static::$password ??= Hash::make('password'),
            'remember_token'     => Str::random(10),
            'last_login_at'      => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['email_verified_at' => now()]);
    }

    public function instructor(): static
    {
        return $this->state(fn () => ['email_verified_at' => now()]);
    }

    public function student(): static
    {
        return $this->state(fn () => ['email_verified_at' => now()]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
