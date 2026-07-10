<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * تجهيز البيانات قبل التحقق
     */
    protected function prepareForValidation()
    {
        if ($this->full_name) {
            // استخراج أول كلمتين من الاسم الكامل
            $words = explode(' ', trim($this->full_name));
            $firstNameTwo = implode(' ', array_slice($words, 0, 2));

            $this->merge([
                'name' => $firstNameTwo,
                // تنظيف الإيميل لضمان عدم وجود مسافات أو حروف كبيرة
                'email' => strtolower(trim($this->email)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // حقل full_name أساسي الآن
            'full_name' => ['required', 'string', 'max:255', 'min:10'],

            // حقل name يتم تعبئته آلياً في prepareForValidation
            'name' => ['required', 'string', 'max:255'],

            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::when(
                    $this->role === 'instructor',
                    Rule::unique('instructors', 'phone')
                ),
            ],
            'role' => ['required', Rule::in(['student', 'instructor'])],

            // بيانات مشتركة اختياري حالياً (للطالب)
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],

            // بيانات الطالب فقط
            'group_id' => ['nullable',  'exists:learning_groups,id'],

            // بيانات المحاضر فقط (إلزامية)
            'bio' => ['required_if:role,instructor', 'nullable', 'string', 'min:20'],
            'field' => ['required_if:role,instructor', 'nullable', 'string'], // المجال
            'status' => ['required_if:role,instructor', 'nullable', Rule::in(['active', 'inactive'])],
            'insta_url' => ['nullable', 'url'],
            'linkedin_url' => ['nullable', 'url'],
            'facebook_url' => ['nullable', 'url'],
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'الاسم الكامل',
            'name' => 'اسم المستخدم',
            'specialty' => 'التخصص/المجال',
            'status' => 'حالة الحساب',
        ];
    }
}
