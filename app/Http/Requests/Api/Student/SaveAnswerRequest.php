<?php

namespace App\Http\Requests\Api\Student;

use App\Models\Question;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAnswerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $question = Question::find($this->input('question_id'));
        $type = $question?->type ?? Question::TYPE_MCQ;

        return [
            'attempt_id' => 'required|exists:exam_attempts,id',
            'question_id' => 'required|exists:questions,id',
            'choice_id' => $type === Question::TYPE_MCQ
                ? [
                    'required',
                    Rule::exists('choices', 'id')->where(function ($query) {
                        $query->where('question_id', $this->question_id);
                    }),
                ]
                : 'prohibited',
            'answer_text' => $type === Question::TYPE_ESSAY
                ? 'required|string|max:10000'
                : 'prohibited',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $question = Question::find($this->input('question_id'));

            if (! $question) {
                return;
            }

            if ($question->isEssay() && ! filled(trim((string) $this->input('answer_text', '')))) {
                $validator->errors()->add('answer_text', 'Essay answer cannot be empty.');
            }
        });
    }
}
