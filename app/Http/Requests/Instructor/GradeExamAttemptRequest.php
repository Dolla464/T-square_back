<?php

namespace App\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class GradeExamAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => 'required|array|min:1',
            'answers.*.answer_id' => 'required|integer|exists:answers,id',
            'answers.*.marks_earned' => 'required|numeric|min:0',
        ];
    }
}
