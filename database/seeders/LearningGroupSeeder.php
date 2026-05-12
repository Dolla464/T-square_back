<?php

namespace Database\Seeders;

use App\Models\LearningGroup;
use Illuminate\Database\Seeder;

class LearningGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LearningGroup::factory(10)->create();
    }
}
