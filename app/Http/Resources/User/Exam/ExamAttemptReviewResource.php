<?php

namespace App\Http\Resources\User\Exam;

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
            $selectedId = $answer?->choice_id;
            $correctChoice = $question->choices->firstWhere('is_correct', true);

            $resultStatus = match (true) {
                $answer === null => 'unanswered',
                $answer->is_correct => 'correct',
                default => 'incorrect',
            };

            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'question_image' => $question->question_image_url,
                'question_code' => $question->question_code,
                'question_code_language' => $question->question_code_language,
                'marks' => $question->marks,
                'result_status' => $resultStatus,
                'selected_choice_id' => $selectedId,
                'correct_choice_id' => $correctChoice?->id,
                'marks_earned' => $answer?->marks_earned ?? 0,
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
            'unanswered' => $questionItems->where('result_status', 'unanswered')->count(),
        ];

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
}
