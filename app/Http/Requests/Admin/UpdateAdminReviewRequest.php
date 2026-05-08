<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id'         => ['sometimes', 'required', 'exists:courses,id'],
            'student_id'        => ['sometimes', 'required', 'exists:students,id'],
            'instructor_id'     => ['sometimes', 'required', 'exists:instructors,id'],
            'content_rating'    => ['sometimes', 'required', 'numeric', 'min:0', 'max:5'],
            'instructor_rating' => ['sometimes', 'required', 'numeric', 'min:0', 'max:5'],
            'center_rating'     => ['sometimes', 'required', 'numeric', 'min:0', 'max:5'],
            'overall_comment'   => ['sometimes', 'required', 'string', 'max:1000'],
        ];
    }
}
