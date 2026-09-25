<?php

namespace App\Http\Requests\Api\Student;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordQuestionTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->user()->student;

        if (! $student) {
            return false;
        }

        return $student->examAttempts()
            ->where('id', $this->attempt_id)
            ->where('status', 'ongoing')
            ->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attempt_id' => 'required|integer|exists:exam_attempts,id',
            'question_id' => 'required|integer|exists:questions,id',
            'time_spent_seconds' => 'required|integer|min:0|max:7200',
        ];
    }
}
