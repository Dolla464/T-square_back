<?php

namespace Database\Seeders;

use App\Models\CourseReview;
use App\Models\Enrollment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CourseReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // هنجيب فقط الطلاب اللي خلصوا الكورسات
        $completedEnrollments = Enrollment::where('is_completed', true)->with('course')->get();

        foreach ($completedEnrollments as $enrollment) {
            CourseReview::create([
                'course_id' => $enrollment->course_id,
                'student_id' => $enrollment->student_id,
                'instructor_id' => $enrollment->course->instructor_id,
                'content_rating' => rand(3, 5),
                'instructor_rating' => rand(4, 5),
                'center_rating' => rand(3, 5),
                'rating' => rand(3, 5),
                'overall_comment' => 'تجربة ممتازة جداً في كورس ' . $enrollment->course->title,
            ]);
        }
    }
}
