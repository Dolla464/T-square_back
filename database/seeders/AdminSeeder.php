<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // تكريت أدمن واحد أساسي ليك للتجربة
        Admin::factory()->create([
            'full_name' => 'Adel Admin',
            'user_id' => \App\Models\User::where('email', 'adel@example.com')->first()->id ?? \App\Models\User::factory()
        ]);

        // وتكريت 2 أدمن كمان عشوائيين
        Admin::factory(2)->create();
    }
}
