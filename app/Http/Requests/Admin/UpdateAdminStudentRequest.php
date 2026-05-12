<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'enrollment_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'enrollment_number')->ignore($this->route('student')),
            ],
            'group_id' => ['sometimes', 'required', 'nullable', 'exists:learning_groups,id'],
            'gender' => ['sometimes', 'required', Rule::in(['male', 'female'])],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],

            // حقل الهاتف ممنوع تعديله
            'phone' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.prohibited' => 'لا يمكن تعديل رقم الهاتف من هنا.',
        ];
    }
}
