<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CourseTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = Tag::all();

        Course::all()->each(function ($course) use ($tags) {

            $randomTags = $tags->random(rand(1, 3))->pluck('id');

            $course->tags()->syncWithoutDetaching($randomTags);
        });
    }
}
