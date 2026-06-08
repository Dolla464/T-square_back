<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\ExamAttempt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AnswerSeeder
 * ------------
 * يُسجّل إجابات الطلاب لكل محاولة امتحان، ويضبط:
 *  - is_correct بناءً على الاختيار الحقيقي
 *  - marks_earned بناءً على درجة السؤال
 */
class AnswerSeeder extends Seeder
{
    public function run(): void
    {
        // نجلب فقط الأسئلة المرتبطة بالمحاولة عبر attempt_questions
        $attempts = ExamAttempt::with([
            'questions.choices', // from attempt_questions pivot
        ])->get();

        if ($attempts->isEmpty()) {
            $this->command->warn('لا توجد محاولات امتحانات — شغّل ExamAttemptSeeder أولاً.');
            return;
        }

        $answersCreated = 0;
        $answerRows     = [];

        foreach ($attempts as $attempt) {
            // إذا لم تكن هناك أسئلة في attempt_questions، انتقل للتالي
            if ($attempt->questions->isEmpty()) {
                continue;
            }

            foreach ($attempt->questions as $question) {
                // تجنب التكرار
                $alreadyAnswered = Answer::where('attempt_id', $attempt->id)
                    ->where('question_id', $question->id)
                    ->exists();

                if ($alreadyAnswered) {
                    continue;
                }

                if ($question->choices->isEmpty()) {
                    continue;
                }

                // 65% احتمال أن يختار الطالب الإجابة الصحيحة
                $correctChoice = $question->choices->firstWhere('is_correct', true);
                $wrongChoices  = $question->choices->where('is_correct', false);

                $chooseCorrect  = rand(1, 100) <= 65;
                $selectedChoice = ($chooseCorrect && $correctChoice)
                    ? $correctChoice
                    : ($wrongChoices->isNotEmpty() ? $wrongChoices->random() : $question->choices->random());

                $isCorrect   = (bool) $selectedChoice->is_correct;
                $marksEarned = $isCorrect ? ($question->marks ?? 0) : 0;

                $answerRows[] = [
                    'attempt_id'   => $attempt->id,
                    'question_id'  => $question->id,
                    'choice_id'    => $selectedChoice->id,
                    'is_correct'   => $isCorrect,
                    'marks_earned' => $marksEarned,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];

                $answersCreated++;

                // إدخال دفعات لتحسين الأداء
                if (count($answerRows) >= 200) {
                    Answer::insert($answerRows);
                    $answerRows = [];
                }
            }
        }

        if (!empty($answerRows)) {
            Answer::insert($answerRows);
        }

        $this->command->info("✓ AnswerSeeder: تم تسجيل {$answersCreated} إجابة.");
    }
}
