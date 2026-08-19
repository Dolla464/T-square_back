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

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->email)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                Rule::prohibitedIf(fn () => $this->user()?->hasRole('receptionist')),
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('student')->user_id),
            ],
            'enrollment_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'enrollment_number')->ignore($this->route('student')),
            ],
            'gender' => ['sometimes', 'required', Rule::in(['male', 'female'])],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'qualification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guardian_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'national_id' => [
                'sometimes',
                'nullable',
                'digits:14',
                Rule::unique('students', 'national_id')->ignore($this->route('student')),
            ],
            'address' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],

            // حقل الهاتف ممنوع تعديله
            'phone' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.prohibited' => 'لا يمكن تعديل رقم الهاتف من هنا.',
            'email.prohibited' => 'Email cannot be updated from this endpoint.',
            'email.unique' => 'This email is already registered.',
            'national_id.digits' => 'National ID must be exactly 14 digits.',
            'national_id.unique' => 'This national ID is already registered.',
        ];
    }
}
