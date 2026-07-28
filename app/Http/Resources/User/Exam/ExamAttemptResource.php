<?php

namespace App\Http\Resources\User\Exam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attemptQuestions = $this->whenLoaded('questions', fn () => $this->questions, collect());
        $attemptMaxMarks = (float) $attemptQuestions->sum('marks');
        $examTotalMarks = (float) $this->exam->total_marks;
        $attemptPassingMark = $examTotalMarks > 0
            ? round(($this->exam->passing_mark / $examTotalMarks) * $attemptMaxMarks, 2)
            : 0.0;

        return [
            'attempt_id' => $this->id,
            'exam_title' => $this->exam->title,
            'duration' => $this->exam->duration,
            'started_at' => $this->started_at->toDateTimeString(),
            'status' => $this->status,
            'total_questions' => $attemptQuestions->count(),
            'questions_per_attempt' => $this->exam->questions_per_attempt,
            'total_marks' => $attemptMaxMarks,
            'attempt_max_marks' => $attemptMaxMarks,
            'exam_total_marks' => $examTotalMarks,
            'passing_mark' => $attemptPassingMark,
            'attempt_passing_mark' => $attemptPassingMark,
            'user_answers' => $this->whenLoaded('answers', fn () => $this->answers->pluck('choice_id', 'question_id'), []),
            'questions' => QuestionResource::collection($attemptQuestions),
        ];
    }
}
