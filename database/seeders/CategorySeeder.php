<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // تكريت 5 أقسام رئيسية
        Category::factory(5)->create()->each(function ($parent) {
            // تحت كل واحد، كريت 3 أقسام فرعية
            Category::factory(3)->create([
                'parent_id' => $parent->id
            ]);
        });
    }
}
