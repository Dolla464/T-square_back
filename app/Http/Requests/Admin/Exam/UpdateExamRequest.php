<?php

namespace App\Http\Requests\Admin\Exam;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'course_id'         => 'required|exists:courses,id',
            'title'             => 'required|string|max:100',
            'description'       => 'nullable|string',
            'duration'          => 'required|integer|min:1',
            'total_marks'       => 'required|numeric|min:0',
            'passing_mark'      => 'required|numeric|min:0|lte:total_marks',
            'is_active'         => 'boolean',
            'is_final'          => 'boolean',
            'max_attempts'      => 'integer|min:1',
            'shuffle_questions' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'passing_mark.lte' => 'The passing mark cannot be higher than the total marks of the exam.',
            'course_id.exists' => 'The specified course does not exist in the system.',
        ];
    }
}