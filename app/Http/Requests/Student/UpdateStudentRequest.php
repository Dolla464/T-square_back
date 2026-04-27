<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentId = $this->route('student')?->id;

        return [
            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
                Rule::unique('students', 'user_id')->ignore($studentId),
            ],
            'full_name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'enrollment_number' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('students', 'enrollment_number')->ignore($studentId),
            ],
            'phone' => ['prohibited'],
            'group_id' => ['sometimes', 'nullable', 'integer', 'exists:learning_groups,id'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', 'in:male,female'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive,suspended'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'المستخدم مطلوب',
            'user_id.exists' => 'المستخدم غير موجود',
            'user_id.unique' => 'هذا المستخدم مرتبط بطالب آخر بالفعل',
            'full_name.required' => 'الاسم الكامل مطلوب',
            'full_name.min' => 'الاسم الكامل يجب أن يكون 3 أحرف على الأقل',
            'enrollment_number.required' => 'رقم القيد مطلوب',
            'enrollment_number.unique' => 'رقم القيد مستخدم بالفعل',
            'phone.prohibited' => 'لا يمكن تحديث رقم الهاتف',
            'group_id.required' => 'المجموعة مطلوبة',
            'group_id.exists' => 'المجموعة غير موجودة',
            'avatar.required' => 'الصورة مطلوبة',
            'gender.required' => 'النوع مطلوب',
            'gender.in' => 'النوع يجب أن يكون male أو female',
            'status.required' => 'الحالة مطلوبة',
            'status.in' => 'الحالة يجب أن تكون active أو inactive أو suspended',
        ];
    }
}
