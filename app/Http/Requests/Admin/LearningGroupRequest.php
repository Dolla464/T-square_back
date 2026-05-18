<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LearningGroupRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'group_name'         => 'required|string|max:255',
            'course_id'          => 'required|exists:courses,id',
            'instructor_id'      => 'required|exists:instructors,id',
            // Flat array of student IDs to assign/keep in the group
            'student_ids'        => 'nullable|array',
            'student_ids.*'      => 'integer|exists:students,id',
            // Keyed object { "student_id": bool } — keys are string-cast integers from JSON
            'student_statuses'   => 'nullable|array',
            'student_statuses.*' => 'boolean',
        ];
    }
}
