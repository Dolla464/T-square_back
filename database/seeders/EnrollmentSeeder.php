<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::all();
        $courses = Course::all();

        $students->each(function ($student) use ($courses) {

            $randomCourses = $courses->unique('id')->random(min(5, $courses->count()));

            foreach ($randomCourses as $course) {

                $exists = Enrollment::where('student_id', $student->id)
                    ->where('course_id', $course->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                Enrollment::create([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'order_id' => null,
                    'price_paid' => $course->price ?? 0,
                    'is_completed' => false,
                ]);
            }
        });
    }
}
