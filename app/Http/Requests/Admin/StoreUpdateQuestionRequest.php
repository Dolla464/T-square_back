<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_id' => 'required|exists:exams,id',
            'question_text' => 'nullable|string',
            'question_image' => 'nullable|string|max:500',
            'question_code' => 'nullable|string|max:10000',
            'question_code_language' => 'nullable|string|max:50|required_with:question_code',
            'marks' => 'required|numeric|min:0.5',

            'choices' => 'required|array|min:2|max:6',
            'choices.*.choice_text' => 'required|string|max:255',
            'choices.*.is_correct' => 'required|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $choices = $this->input('choices', []);
            $correctCount = collect($choices)->where('is_correct', true)->count();

            if ($correctCount !== 1) {
                $validator->errors()->add('choices', 'You must select only one correct answer for the question.');
            }

            $hasText = filled(trim((string) $this->input('question_text', '')));
            $hasImage = filled($this->input('question_image'));
            $hasCode = filled(trim((string) $this->input('question_code', '')));

            if (! $hasText && ! $hasImage && ! $hasCode) {
                $validator->errors()->add(
                    'question_text',
                    'Provide at least one of question text, image, or code.'
                );
            }
        });
    }
}
