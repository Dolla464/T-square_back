<?php

namespace Database\Seeders;

use App\Models\Solution;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SolutionTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = Tag::all();
        $solutions = Solution::all();

        if ($tags->isEmpty() || $solutions->isEmpty()) {
            $this->command->warn('تأكد من وجود بيانات في جداول tags و solutions أولاً!');
            return;
        }

        $solutions->each(function ($solution) use ($tags) {
            // ربط كل حل بـ 2 لـ 4 تاجات عشوائية
            $solution->tags()->attach(
                $tags->random(rand(2, 4))->pluck('id')->toArray()
            );
        });
    }
}
