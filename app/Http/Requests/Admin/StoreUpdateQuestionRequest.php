<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUpdateQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_id'               => 'required|exists:exams,id',
            'question_text'         => 'required|string',
            'marks'                 => 'required|numeric|min:0.5',

            // Check the nested choices array
            'choices'               => 'required|array|min:2|max:6', // At least 2 choices and maximum 6 choices
            'choices.*.choice_text' => 'required|string|max:255',
            'choices.*.is_correct'  => 'required|boolean',
        ];
    }

    /**
     * Smart validation: Check that the admin has selected only one correct answer
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $choices = $this->input('choices', []);
            $correctCount = collect($choices)->where('is_correct', true)->count();

            if ($correctCount !== 1) {
                $validator->errors()->add('choices', 'You must select only one correct answer for the question.');
            }
        });
    }
}
