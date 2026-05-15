<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],

            // حقول محظور تعديلها
            'email' => ['prohibited'],
            'phone' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.prohibited' => 'Email can not be changed.',
            'phone.prohibited' => 'Phone can not be changed.',
        ];
    }
}
