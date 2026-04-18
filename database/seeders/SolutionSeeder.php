<?php

namespace Database\Seeders;

use App\Models\Solution;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SolutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Solution::factory(6)->create();
    }
}
