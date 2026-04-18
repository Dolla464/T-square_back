<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * تحديد هل المستخدم مصرح له بإرسال هذا الطلب
     * (بما إن ده للأدمن، هنخليه true حالياً لو محمي بـ Middleware)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق (Validation Rules)
     */
    public function rules(): array
    {
        return [
            // بيانات جدول users
            'name'     => ['required', 'string', 'max:255'], // لاسم المستخدم (Username)
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'min:8'],
            'role'     => ['required', 'exists:roles,name'],

            // بيانات مشتركة (موجودة في الجدولين)
            'full_name' => ['nullable', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'gender'    => ['nullable', Rule::in(['male', 'female'])],
            'avatar'    => ['nullable', 'string'],

            // بيانات الطالب فقط
            'group_id' => ['nullable', 'exists:learning_groups,id'],

            // بيانات المحاضر فقط
            'bio'           => ['required_if:role,instructor','string','min:20'],
            'insta_url'     => ['required_if:role,instructor', 'url'],
            'linkedin_url'  => ['required_if:role,instructor', 'url'],
            'facebook_url'  => ['required_if:role,instructor', 'url'],
        ];
    }
}
