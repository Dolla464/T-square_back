<?php

namespace App\Services\Exam;

use App\Models\ExamAttempt;
use App\Models\Question;
use App\Services\User\ExamService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamGradingService
{
    public function __construct(
        private ExamService $examService,
    ) {}

    public function getPendingGradingForInstructor(int $instructorId): Collection
    {
        return ExamAttempt::query()
            ->where('status', ExamAttempt::STATUS_AWAITING_GRADING)
            ->whereHas('exam.course.instructors', fn ($query) => $query->where('instructors.id', $instructorId))
            ->with([
                'student:id,full_name,user_id',
                'student.user:id,email',
                'exam:id,title,course_id',
                'exam.course:id,title',
            ])
            ->orderByDesc('finished_at')
            ->get()
            ->map(fn (ExamAttempt $attempt) => [
                'attempt_id' => $attempt->id,
                'exam_id' => $attempt->exam_id,
                'exam_title' => $attempt->exam?->title,
                'course_title' => $attempt->exam?->course?->title,
                'student_id' => $attempt->student_id,
                'student_name' => $attempt->student?->full_name ?? $attempt->student?->user?->name,
                'student_email' => $attempt->student?->user?->email,
                'score' => $attempt->score,
                'status' => $attempt->status,
                'finished_at' => $attempt->finished_at?->format('Y-m-d H:i'),
            ]);
    }

    public function getAttemptForGrading(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->status !== ExamAttempt::STATUS_AWAITING_GRADING) {
            abort(409, 'This attempt is not awaiting grading.');
        }

        return $this->examService->getAttemptReview($attempt->id);
    }

    public function gradeAttempt(int $attemptId, array $answerMarks, int $instructorId): array
    {
        return DB::transaction(function () use ($attemptId, $answerMarks, $instructorId) {
            $attempt = ExamAttempt::query()
                ->whereKey($attemptId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($attempt->status !== ExamAttempt::STATUS_AWAITING_GRADING) {
                abort(409, 'This attempt is not awaiting grading (already finalized, or still in progress).');
            }

            $attempt->loadMissing(['questionsWithTrashed', 'answers.question']);

            $essayQuestionIds = $attempt->questionsWithTrashed
                ->filter(fn (Question $question) => $question->isEssay())
                ->pluck('id');

            $gradableAnswers = $attempt->answers
                ->whereIn('question_id', $essayQuestionIds)
                ->filter(fn ($answer) => filled(trim((string) $answer->answer_text)));

            $provided = collect($answerMarks)->keyBy('answer_id');

            $missing = $gradableAnswers->pluck('id')->diff($provided->keys());
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'answers' => 'All submitted essay answers must be graded before finalizing this attempt.',
                ]);
            }

            foreach ($gradableAnswers as $answer) {
                if (! $provided->has($answer->id)) {
                    continue;
                }

                $marks = (float) $provided->get($answer->id)['marks_earned'];
                $maxMarks = (float) ($answer->question?->marks ?? 0);

                if ($marks < 0 || $marks > $maxMarks) {
                    throw ValidationException::withMessages([
                        "answers.{$answer->id}.marks_earned" => "Marks must be between 0 and {$maxMarks}.",
                    ]);
                }

                $answer->forceFill([
                    'marks_earned' => $marks,
                    'is_correct' => null,
                    'graded_at' => now(),
                    'graded_by' => $instructorId,
                ])->save();
            }

            $totalScore = (float) $attempt->answers()->sum('marks_earned');

            $attempt->forceFill([
                'graded_at' => now(),
                'graded_by' => $instructorId,
            ])->save();

            return $this->examService->finalizeAttemptScore($attempt, $totalScore);
        });
    }
}
