<?php

namespace App\Services\User;

use App\Models\Answer;
use App\Models\Choice;
use App\Models\ExamAttempt;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class ExamService
{
    /**
     * get all available exams for a student
     *
     * @param [type] $student
     * @return Collection
     */
    public function getAvailableExams($student)
    {
        // بنجيب الامتحانات المتاحة بناءً على الكورسات اللي الطالب مشترك فيها ويكون الامتحان active
        return $student->availableExams()->where('is_active', true)->with('course')->get();
    }
    /**
     *  start new attempt
     */
    public function startAttempt(int $studentId, int $examId): ExamAttempt
    {
        // ممكن تضيف هنا تشيك: هل الطالب بدأ الامتحان ده قبل كده؟

        return ExamAttempt::create([
            'student_id' => $studentId, // تأكد إن العمود في الداتابيز اسمه student_id
            'exam_id'    => $examId,
            'started_at' => now(),
            'status'     => 'ongoing'
        ]);
    }

    /**
     *  save one question answer
     */
    public function saveAnswer(int $attemptId, int $questionId, int $choiceId)
    {
        $choice = Choice::findOrFail($choiceId);
        $question = Question::findOrFail($questionId);

        // استخدام updateOrCreate عشان لو الطالب غير رأيه في نفس السؤال
        return Answer::updateOrCreate(
            ['attempt_id' => $attemptId, 'question_id' => $questionId],
            [
                'choice_id'    => $choiceId,
                'is_correct'   => $choice->is_correct,
                'marks_earned' => $choice->is_correct ? $question->marks : 0
            ]
        );
    }

    /**
     * إنهاء الامتحان وحساب النتيجة النهائية
     */
    public function completeAttempt($attemptId)
    {
        $attempt = ExamAttempt::with('exam')->findOrFail($attemptId);

        if ($attempt->status !== 'ongoing') {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt) {
            $totalScore = $attempt->answers()->sum('marks_earned');

            // تحديد إذا كان ناجحاً أم لا
            $isPassed = $totalScore >= $attempt->exam->passing_mark;

            $attempt->update([
                'score'       => $totalScore,
                'status'      => 'completed',
                'finished_at' => now()
            ]);

            // بنرجع الداتا ومعاها العلم (Flag) بتاع النجاح
            return [
                'score'        => $totalScore,
                'total_marks'  => $attempt->exam->total_marks,
                'passing_mark' => $attempt->exam->passing_mark,
                'is_passed'    => $isPassed,
                'status'       => 'completed'
            ];
        });
    }

    public function getStudentResults($studentId)
    {
        return ExamAttempt::where('student_id', $studentId)
            ->whereIn('status', ['completed', 'timed_out'])
            ->with('exam.course')
            ->orderBy('finished_at', 'desc')
            ->get();
    }
}
