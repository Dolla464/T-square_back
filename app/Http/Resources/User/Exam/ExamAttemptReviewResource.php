<?php

namespace App\Http\Resources\User\Exam;

use App\Services\Exam\ExamIntegrityService;
use App\Services\User\ExamService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ExamService $examService */
        $examService = app(ExamService::class);

        $answersByQuestion = $this->answers->keyBy('question_id');
        $questions = $this->resolveAttemptQuestions()
            ->unique('id')
            ->values();

        $attemptMaxMarks = $examService->getAttemptMaxMarks($this->resource);
        $attemptPassingMark = $examService->getAttemptPassingMark($this->resource);

        $questionItems = $questions->map(function ($question) use ($answersByQuestion) {
            $answer = $answersByQuestion->get($question->id);

            if ($question->isEssay()) {
                $resultStatus = $this->resolveEssayResultStatus($question, $answer);

                return [
                    'id' => $question->id,
                    'type' => $question->type,
                    'question_text' => $question->question_text,
                    'question_image' => $question->question_image_url,
                    'question_code' => $question->question_code,
                    'question_code_language' => $question->question_code_language,
                    'marks' => $question->marks,
                    'result_status' => $resultStatus,
                    'answer_id' => $answer?->id,
                    'answer_text' => $answer?->answer_text,
                    'marks_earned' => $answer?->marks_earned ?? 0,
                    'time_spent_seconds' => $question->pivot?->time_spent_seconds !== null
                        ? (int) $question->pivot->time_spent_seconds
                        : null,
                ];
            }

            $selectedId = $answer?->choice_id;
            $correctChoice = $question->choices->firstWhere('is_correct', true);

            $resultStatus = match (true) {
                $answer === null => 'unanswered',
                $answer->is_correct === true => 'correct',
                default => 'incorrect',
            };

            return [
                'id' => $question->id,
                'type' => $question->type ?? 'mcq',
                'question_text' => $question->question_text,
                'question_image' => $question->question_image_url,
                'question_code' => $question->question_code,
                'question_code_language' => $question->question_code_language,
                'marks' => $question->marks,
                'result_status' => $resultStatus,
                'selected_choice_id' => $selectedId,
                'correct_choice_id' => $correctChoice?->id,
                'marks_earned' => $answer?->marks_earned ?? 0,
                'time_spent_seconds' => $question->pivot?->time_spent_seconds !== null
                    ? (int) $question->pivot->time_spent_seconds
                    : null,
                'choices' => $question->choices->map(function ($choice) use ($selectedId) {
                    return [
                        'id' => $choice->id,
                        'choice_text' => $choice->choice_text,
                        'state' => match (true) {
                            $choice->is_correct && $selectedId === $choice->id => 'correct_selected',
                            $choice->is_correct => 'correct',
                            $selectedId === $choice->id => 'wrong_selected',
                            default => 'neutral',
                        },
                    ];
                })->values(),
            ];
        })->values();

        $summary = [
            'correct' => $questionItems->where('result_status', 'correct')->count(),
            'incorrect' => $questionItems->where('result_status', 'incorrect')->count(),
            'partial_credit' => $questionItems->where('result_status', 'partial_credit')->count(),
            'unanswered' => $questionItems->where('result_status', 'unanswered')->count(),
            'pending_grading' => $questionItems->where('result_status', 'pending_grading')->count(),
            'graded' => $questionItems->where('result_status', 'graded')->count(),
        ];

        $user = $request->user();
        $isStaff = $user !== null && $user->hasAnyRole(['admin', 'instructor', 'receptionist']);

        /** @var ExamIntegrityService $integrityService */
        $integrityService = app(ExamIntegrityService::class);

        return [
            'attempt_id' => $this->id,
            'exam_id' => $this->exam_id,
            'exam_title' => $this->exam->title,
            'status' => $this->status,
            'score' => $this->score,
            'total_marks' => $attemptMaxMarks,
            'attempt_max_marks' => $attemptMaxMarks,
            'passing_mark' => $attemptPassingMark,
            'attempt_passing_mark' => $attemptPassingMark,
            'exam_total_marks' => $this->exam->total_marks,
            'exam_passing_mark' => $this->exam->passing_mark,
            'finished_at' => $this->finished_at?->format('Y-m-d H:i'),
            'summary' => $summary,
            'questions' => $questionItems,
            'integrity_events' => $this->when(
                $isStaff && $this->relationLoaded('integrityEvents'),
                fn () => ExamAttemptIntegrityEventResource::collection($this->integrityEvents),
            ),
            'integrity_summary' => $this->when(
                $isStaff && $this->relationLoaded('integrityEvents'),
                fn () => $integrityService->buildSummary($this->integrityEvents),
            ),
        ];
    }

    private function resolveAttemptQuestions()
    {
        if ($this->relationLoaded('questions') && $this->questions->isNotEmpty()) {
            return $this->questions;
        }

        if ($this->relationLoaded('questionsWithTrashed')) {
            return $this->questionsWithTrashed;
        }

        if ($this->relationLoaded('questions')) {
            return $this->questions;
        }

        return collect();
    }

    private function resolveEssayResultStatus($question, $answer): string
    {
        if ($answer === null || ! filled(trim((string) ($answer->answer_text ?? '')))) {
            return 'unanswered';
        }

        if ($answer->graded_at === null) {
            return 'pending_grading';
        }

        $marksEarned = (float) ($answer->marks_earned ?? 0);
        $maxMarks = (float) $question->marks;

        if ($marksEarned >= $maxMarks) {
            return 'correct';
        }

        if ($marksEarned > 0) {
            return 'partial_credit';
        }

        return 'incorrect';
    }
}
