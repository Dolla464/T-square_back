<?php

namespace App\Http\Requests\Profile;

use App\Support\SuspiciousRequestLogger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public const ALLOWED = [
        'gender',
        'avatar',
        'phone',
        'field',
        'bio',
        'insta_url',
        'linkedin_url',
        'facebook_url',
        'full_name',
        'name',
        'password',
        'password_confirmation',
        'age',
        'qualification',
        'guardian_phone',
        'national_id',
        'address',
        'notes',
    ];

    private const FORBIDDEN_DETECTORS = [
        'role',
        'status',
        'email',
        'group_id',
        'enrollment_number',
        'email_verified_at',
        'verified',
        'created_by',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $extra = SuspiciousRequestLogger::detectExtraFields($this, self::allowedKeysForUser());
        if ($extra !== []) {
            SuspiciousRequestLogger::log($this, 'forbidden_fields_on_profile_update', $extra);
        }
    }

    public function rules(): array
    {
        $user = $this->user();
        $phoneRules = ['sometimes', 'nullable', 'string', 'max:20'];

        if ($user && $user->role === 'instructor') {
            $phoneRules[] = Rule::unique('instructors', 'phone')->ignore($user->instructor?->id);
        }

        $forbiddenRules = collect(self::FORBIDDEN_DETECTORS)
            ->mapWithKeys(fn (string $field) => [$field => ['prohibited']])
            ->all();

        $rules = array_merge($forbiddenRules, [
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'field' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'insta_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'facebook_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'phone' => $phoneRules,
            'age' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'qualification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guardian_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'national_id' => [
                'sometimes',
                'nullable',
                'digits:14',
                Rule::unique('students', 'national_id')->ignore($user?->student?->id),
            ],
            'address' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if ($user && $user->role === 'student') {
            $rules['name'] = ['prohibited'];
            $rules['full_name'] = ['prohibited'];
        } else {
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['full_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function safePayload(): array
    {
        return $this->safe()->only(self::allowedKeysForUser());
    }

    /**
     * @return array<int, string>
     */
    private function allowedKeysForUser(): array
    {
        $user = $this->user();

        if ($user && $user->role === 'student') {
            return array_values(array_diff(self::ALLOWED, ['full_name', 'name', 'password', 'password_confirmation']));
        }

        return array_values(array_diff(self::ALLOWED, ['password', 'password_confirmation']));
    }

    public function messages(): array
    {
        return [
            'email.prohibited' => 'Email can not be changed.',
            'name.prohibited' => 'Name can not be changed.',
            'full_name.prohibited' => 'Name can not be changed.',
            'phone.unique' => 'This phone number is already in use.',
            'national_id.digits' => 'National ID must be exactly 14 digits.',
            'national_id.unique' => 'This national ID is already registered.',
        ];
    }
}
