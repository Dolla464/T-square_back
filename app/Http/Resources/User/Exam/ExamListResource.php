<?php

namespace App\Http\Resources\User\Exam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Since we added withCount('attempts') in the service, the value is ready as an Attribute
        $attemptsCount = $this->attempts_count ?? 0;

        // Calculate the remaining attempts smartly to handle the null (infinite attempts)
        $remainingAttempts = is_null($this->max_attempts) || $this->max_attempts == 0
            ? 'unlimited'
            : max(0, $this->max_attempts - $attemptsCount);

        // Check if the student has passed any previous attempt for this exam
        $isPassedBefore = $this->attempts()
            ->where('student_id', $request->user()->student->id)
            ->where('status', 'passed')
            ->exists();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'duration' => $this->duration . ' mins',
            'total_marks' => $this->total_marks,
            'passing_mark' => $this->passing_mark,
            'is_final' => (bool) $this->is_final,
            'course_title' => $this->course?->title,

            // The new data for the attempts
            'max_attempts' => $this->max_attempts,
            'attempts_count' => $attemptsCount,
            'remaining_attempts' => $remainingAttempts,
            'has_attempt' => $attemptsCount > 0,
            'is_locked' => $remainingAttempts !== 'unlimited' && $remainingAttempts <= 0,
            'is_passed_before' => $isPassedBefore,

            // Lets the frontend warn the student before they start an empty exam
            'questions_count' => $this->questions_count ?? $this->questions()->count(),
            'has_questions' => ($this->questions_count ?? $this->questions()->count()) > 0,
        ];
    }
}
