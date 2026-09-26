<?php

namespace App\Http\Requests\Admin;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type', Question::TYPE_MCQ);

        return [
            'exam_id' => 'required|exists:exams,id',
            'type' => ['required', Rule::in([Question::TYPE_MCQ, Question::TYPE_ESSAY])],
            'question_text' => 'nullable|string',
            'question_image' => 'nullable|string|max:500',
            'question_code' => 'nullable|string|max:10000',
            'question_code_language' => 'nullable|string|max:50|required_with:question_code',
            'marks' => 'required|integer|min:1',

            'choices' => $type === Question::TYPE_MCQ ? 'required|array|min:2|max:6' : 'prohibited',
            'choices.*.choice_text' => 'required_with:choices|string|max:255',
            'choices.*.is_correct' => 'required_with:choices|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type', Question::TYPE_MCQ);

            if ($type === Question::TYPE_MCQ) {
                $choices = $this->input('choices', []);
                $correctCount = collect($choices)->where('is_correct', true)->count();

                if ($correctCount !== 1) {
                    $validator->errors()->add('choices', 'You must select only one correct answer for the question.');
                }
            }

            $hasText = filled(trim((string) $this->input('question_text', '')));

            if (! $hasText) {
                $validator->errors()->add(
                    'question_text',
                    'Question text is required.'
                );
            }

            $question = $this->route('question');
            if ($question instanceof Question) {
                if ((int) $this->input('exam_id') !== (int) $question->exam_id) {
                    $validator->errors()->add(
                        'exam_id',
                        'Questions cannot be moved to another exam.'
                    );
                }
            }

            if ($question instanceof Question && $question->studentAnswers()->exists()) {
                $requestedType = $this->input('type');
                if ($requestedType !== null && $requestedType !== $question->type) {
                    $validator->errors()->add(
                        'type',
                        'Question type cannot be changed after students have answered this question.'
                    );
                }
            }
        });
    }
}
