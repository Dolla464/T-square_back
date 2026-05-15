<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\ExamAttempt;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ExamAttemptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // بنجيب الطلاب اللي مشتركين في كورسات بس
        $enrollments = Enrollment::with(['student', 'course.exams'])->get();

        if ($enrollments->isEmpty()) {
            $this->command->warn('يا عادل لازم تشغل الـ EnrollmentSeeder والـ ExamSeeder الأول!');

            return;
        }

        foreach ($enrollments as $enrollment) {
            // لكل كورس الطالب مشترك فيه، هنشوف لو ليه امتحانات
            foreach ($enrollment->course->exams as $exam) {

                // هنعمل محاولة امتحان واحدة لكل طالب في كل امتحان تابع لكورسه
                $startedAt = Carbon::now()->subDays(rand(1, 30))->subMinutes(rand(60, 200));

                ExamAttempt::create([
                    'student_id' => $enrollment->student_id,
                    'exam_id' => $exam->id,
                    'started_at' => $startedAt,
                    // بنفترض إن الامتحان مدته عشوائية بين 20 لـ 50 دقيقة
                    'finished_at' => (clone $startedAt)->addMinutes(rand(20, 50)),
                    // درجة عشوائية من إجمالي درجات الامتحان
                    'score' => rand(0, (int) $exam->total_marks),
                ]);
            }
        }

        $this->command->info('تم إنشاء محاولات امتحانات للطلاب المشتركين بنجاح!');
    }
}
