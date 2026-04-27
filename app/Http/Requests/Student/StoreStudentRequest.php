<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:students,user_id'],
            'full_name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'enrollment_number' => ['required', 'string', 'max:255', 'unique:students,enrollment_number'],
            'group_id' => ['nullable', 'integer', 'exists:learning_groups,id'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'المستخدم مطلوب',
            'user_id.exists' => 'المستخدم غير موجود',
            'user_id.unique' => 'هذا المستخدم مرتبط بطالب بالفعل',
            'full_name.required' => 'الاسم الكامل مطلوب',
            'full_name.min' => 'الاسم الكامل يجب أن يكون 3 أحرف على الأقل',
            'phone.required' => 'رقم الهاتف مطلوب',
            'enrollment_number.required' => 'رقم القيد مطلوب',
            'enrollment_number.unique' => 'رقم القيد مستخدم بالفعل',
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
