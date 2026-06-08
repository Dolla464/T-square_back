<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\Question;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ExamAttemptSeeder
 * -----------------
 * - ينشئ محاولة امتحان واحدة لكل طالب في كل امتحان تابع لكورسه
 * - يضبط حقل status بشكل صحيح
 * - يُدخل الأسئلة في جدول attempt_questions
 */
class ExamAttemptSeeder extends Seeder
{
    public function run(): void
    {
        $enrollments = Enrollment::with(['student', 'course.exams.questions'])->get();

        if ($enrollments->isEmpty()) {
            $this->command->warn('لا توجد اشتراكات — شغّل EnrollmentSeeder أولاً.');
            return;
        }

        $attemptsCreated = 0;

        foreach ($enrollments as $enrollment) {
            foreach ($enrollment->course->exams as $exam) {
                // تجنب التكرار
                $exists = ExamAttempt::where('student_id', $enrollment->student_id)
                    ->where('exam_id', $exam->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $startedAt  = Carbon::now()->subDays(rand(1, 30))->subMinutes(rand(60, 200));
                $finishedAt = (clone $startedAt)->addMinutes(rand(20, (int) ($exam->duration ?? 60)));

                $attempt = ExamAttempt::create([
                    'student_id'  => $enrollment->student_id,
                    'exam_id'     => $exam->id,
                    'status'      => 'completed',
                    'started_at'  => $startedAt,
                    'finished_at' => $finishedAt,
                    'score'       => rand(0, (int) ($exam->total_marks ?? 100)),
                ]);

                // إدخال الأسئلة في attempt_questions
                $questions = $exam->questions;
                $limit     = min($exam->questions_per_attempt ?? 10, $questions->count());

                if ($questions->isNotEmpty() && $limit > 0) {
                    $selectedQuestionIds = $questions->random($limit)->pluck('id');

                    $rows = $selectedQuestionIds->map(fn ($qId) => [
                        'exam_attempt_id' => $attempt->id,
                        'question_id'     => $qId,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ])->toArray();

                    DB::table('attempt_questions')->insert($rows);
                }

                $attemptsCreated++;
            }
        }

        $this->command->info("✓ ExamAttemptSeeder: تم إنشاء {$attemptsCreated} محاولة امتحان.");
    }
}
