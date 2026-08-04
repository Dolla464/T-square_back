<?php

namespace App\Http\Resources\User\Exam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $studentId = $request->user()->student->id;
        $attemptsCount = $this->attempts_count ?? 0;

        $remainingAttempts = is_null($this->max_attempts) || $this->max_attempts == 0
            ? 'unlimited'
            : max(0, $this->max_attempts - $attemptsCount);

        $hasOngoingAttempt = $this->attempts()
            ->where('student_id', $studentId)
            ->where('status', 'ongoing')
            ->exists();

        $isPassedBefore = $this->attempts()
            ->where('student_id', $studentId)
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

            'max_attempts' => $this->max_attempts,
            'attempts_count' => $attemptsCount,
            'remaining_attempts' => $remainingAttempts,
            'has_attempt' => $attemptsCount > 0,
            'has_ongoing_attempt' => $hasOngoingAttempt,
            'is_locked' => ! $hasOngoingAttempt && $remainingAttempts !== 'unlimited' && $remainingAttempts <= 0,
            'is_passed_before' => $isPassedBefore,

            'questions_count' => $this->questions_count ?? $this->questions()->count(),
            'has_questions' => ($this->questions_count ?? $this->questions()->count()) > 0,
        ];
    }
}
