<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Exam;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // لكل كورس، هنحط امتحان واحد نهائي مثلاً
        Course::all()->each(function ($course) {
            Exam::factory()->create([
                'course_id' => $course->id,
                'title' => 'Final Exam for '.$course->title,
            ]);
        });
    }
}
