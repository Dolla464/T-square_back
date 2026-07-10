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
        $user = $this->user();
        $phoneRules = ['sometimes', 'nullable', 'string', 'max:20'];

        if ($user && $user->role === 'instructor') {
            $phoneRules[] = Rule::unique('instructors', 'phone')->ignore($user->instructor?->id);
        }

        $rules = [
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'field' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'insta_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'facebook_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'phone' => $phoneRules,

            // حقول محظور تعديلها
            'email' => ['prohibited'],
        ];

        if ($user && $user->role === 'student') {
            $rules['name'] = ['prohibited'];
            $rules['full_name'] = ['prohibited'];
        } else {
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['full_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.prohibited' => 'Email can not be changed.',
            'name.prohibited' => 'Name can not be changed.',
            'full_name.prohibited' => 'Name can not be changed.',
            'phone.unique' => 'This phone number is already in use.',
        ];
    }
}
