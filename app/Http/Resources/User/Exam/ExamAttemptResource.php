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
        // Use the attempt's own question subset (from attempt_questions pivot),
        // not the full exam bank. This ensures total_questions matches questions[].
        $attemptQuestions = $this->whenLoaded('questions', function () {
            $questions = $this->questions;

            if ($this->exam->shuffle_questions) {
                $questions = $questions->shuffle();
            }

            return $questions;
        }, collect());

        return [
            'attempt_id' => $this->id,
            'exam_title' => $this->exam->title,
            'duration' => $this->exam->duration,
            'started_at' => $this->started_at->toDateTimeString(),
            'status' => $this->status,
            'total_questions' => $attemptQuestions->count(),
            'total_marks' => $this->exam->total_marks,
            'user_answers' => $this->whenLoaded('answers', fn () => $this->answers->pluck('choice_id', 'question_id'), []),
            'questions' => QuestionResource::collection($attemptQuestions),
        ];
    }
}
