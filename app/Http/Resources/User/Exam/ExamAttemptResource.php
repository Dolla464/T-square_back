<?php

namespace App\Http\Resources\User\Exam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $questionsQuery = $this->exam->questions()->with('choices');

        if ($this->exam->shuffle_questions) {
            $questionsQuery->inRandomOrder();
        }

        $questions = $questionsQuery->get();

        return [
            'attempt_id' => $this->id,
            'exam_title' => $this->exam->title,
            'duration'   => $this->exam->duration,
            'started_at' => $this->started_at->toDateTimeString(),
            'status'     => $this->status,
            'total_questions' => $questions->count(),
            'total_marks'     => $this->exam->total_marks,
            'user_answers'    => $this->answers->pluck('choice_id', 'question_id'),
            'questions'  => QuestionResource::collection($questions),
        ];
    }
}
