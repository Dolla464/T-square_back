<?php

namespace Database\Factories;

use App\Models\GoogleStorageAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleStorageAccount>
 */
class GoogleStorageAccountFactory extends Factory
{
    protected $model = GoogleStorageAccount::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->safeEmail(),
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'status' => GoogleStorageAccount::STATUS_CONNECTED,
        ];
    }
}
