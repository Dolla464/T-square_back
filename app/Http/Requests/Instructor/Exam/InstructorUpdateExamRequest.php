<?php

namespace App\Http\Requests\Instructor\Exam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstructorUpdateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $instructorId = $this->user()?->instructor?->id;

        return [
            'course_id'             => [
                'required',
                'exists:courses,id',
                Rule::exists('course_instructor', 'course_id')->where('instructor_id', $instructorId),
            ],
            'title'                 => 'required|string|max:100',
            'description'           => 'nullable|string',
            'duration'              => 'required|integer|min:1',
            'total_marks'           => 'required|numeric|min:0',
            'passing_mark'          => 'required|numeric|min:0|lte:total_marks',
            'is_active'             => 'boolean',
            'is_final'              => 'boolean',
            'max_attempts'          => 'integer|min:1',
            'questions_per_attempt' => 'required|integer|min:1',
            'shuffle_questions'     => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'passing_mark.lte' => 'The passing mark cannot be higher than the total marks of the exam.',
            'course_id.exists' => 'The specified course does not belong to you.',
            'questions_per_attempt.min' => 'The number of questions per attempt must be at least 1.',
            'questions_per_attempt.required' => 'The number of questions per attempt is required.',
        ];
    }
}
