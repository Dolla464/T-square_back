<?php

namespace App\Http\Requests\Api\Student;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // بنجيب الطالب المرتبط باليوزر، وبعدين نشوف محاولاته
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
        return [
            //
        ];
    }
}
