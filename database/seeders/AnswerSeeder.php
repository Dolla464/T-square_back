<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\ExamAttempt;
use Illuminate\Database\Seeder;

class AnswerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attempts = ExamAttempt::with('exam.questions.choices')->get();

        foreach ($attempts as $attempt) {
            foreach ($attempt->exam->questions as $question) {
                // الطالب بيختار إجابة عشوائية من الـ Choices بتاعة السؤال ده
                $randomChoice = $question->choices->random();

                Answer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'choice_id' => $randomChoice->id,
                ]);
            }
        }
        $this->command->info('تم تسجيل إجابات الطلاب في المحاولات بنجاح!');
    }
}
