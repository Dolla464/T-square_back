<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // admin user to test the system
        User::factory()->create([
            'name' => 'Adel Admin',
            'email' => 'admin@tsquare.com',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
        ]);

        // student user to test the system
        User::factory()->create([
            'name' => 'Adel Student',
            'email' => 'student@tsquare.com',
            'password' => Hash::make('12345678'),
            'role' => 'student',
        ]);

        // create 5 instructors
        User::factory(5)->create(['role' => 'instructor']);

        // create 20 students
        User::factory(20)->create(['role' => 'student']);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
