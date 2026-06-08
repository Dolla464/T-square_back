<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\LearningGroup;
use Illuminate\Database\Seeder;

/**
 * LearningGroupSeeder
 * --------------------
 * لكل كورس يُنشئ من 1 إلى 2 مجموعة تعلم.
 * المجموعة ترتبط بالمدرب نفسه الذي يدرّس الكورس.
 */
class LearningGroupSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::with('instructor')->get();

        if ($courses->isEmpty()) {
            $this->command->warn('لا توجد كورسات — شغّل CourseSeeder أولاً.');
            return;
        }

        $count = 0;

        foreach ($courses as $course) {
            $groupsPerCourse = rand(1, 2);

            for ($i = 1; $i <= $groupsPerCourse; $i++) {
                LearningGroup::create([
                    'group_name'  => "Batch #{$i} - " . substr($course->title, 0, 20),
                    'course_id'   => $course->id,
                    'instructor_id' => $course->instructor_id,
                    'enrolled_students' => 0,
                ]);

                $count++;
            }
        }

        $this->command->info("✓ LearningGroupSeeder: تم إنشاء {$count} مجموعة تعلم.");
    }
}
