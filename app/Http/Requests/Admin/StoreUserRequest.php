<?php

namespace App\Http\Requests\Admin;

use App\Support\SuspiciousRequestLogger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    private const STUDENT_KEYS = [
        'full_name',
        'name',
        'email',
        'password',
        'phone',
        'gender',
        'avatar',
        'group_id',
        'role',
    ];

    private const INSTRUCTOR_KEYS = [
        'bio',
        'field',
        'status',
        'insta_url',
        'linkedin_url',
        'facebook_url',
    ];

    private const FORBIDDEN_DETECTORS = [
        'verified',
        'email_verified_at',
        'created_by',
        'enrollment_number',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasAnyRole(['admin', 'receptionist']);
    }

    protected function prepareForValidation(): void
    {
        if ($this->full_name) {
            $words = explode(' ', trim((string) $this->full_name));
            $firstNameTwo = implode(' ', array_slice($words, 0, 2));

            $this->merge([
                'name' => $firstNameTwo,
                'email' => strtolower(trim((string) $this->email)),
            ]);
        }

        if ($this->user()?->hasRole('receptionist') && $this->input('role') === 'instructor') {
            SuspiciousRequestLogger::log(
                $this,
                'receptionist_instructor_role_attempt',
                ['role']
            );
        }

        $extra = SuspiciousRequestLogger::detectExtraFields($this, $this->allowedKeysForContext());
        if ($extra !== []) {
            SuspiciousRequestLogger::log($this, 'forbidden_fields_on_staff_user_create', $extra);
        }
    }

    public function rules(): array
    {
        $isReceptionist = $this->user()?->hasRole('receptionist');
        $allowedRoles = $isReceptionist ? ['student'] : ['student', 'instructor'];

        $forbiddenRules = collect(self::FORBIDDEN_DETECTORS)
            ->mapWithKeys(fn (string $field) => [$field => ['prohibited']])
            ->all();

        return array_merge($forbiddenRules, [
            'full_name' => ['required', 'string', 'max:255', 'min:10'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::when(
                    $this->input('role') === 'instructor',
                    Rule::unique('instructors', 'phone')
                ),
            ],
            'role' => ['required', Rule::in($allowedRoles)],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'group_id' => ['nullable', 'exists:learning_groups,id'],
            'bio' => ['required_if:role,instructor', 'nullable', 'string', 'min:20'],
            'field' => ['required_if:role,instructor', 'nullable', 'string'],
            'status' => ['required_if:role,instructor', 'nullable', Rule::in(['active', 'inactive'])],
            'insta_url' => ['nullable', 'url'],
            'linkedin_url' => ['nullable', 'url'],
            'facebook_url' => ['nullable', 'url'],
        ]);
    }

    public function safePayload(): array
    {
        $payload = $this->safe()->only($this->allowedKeysForContext());

        if ($this->user()?->hasRole('receptionist')) {
            $payload['role'] = 'student';
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    public function allowedKeysForContext(): array
    {
        if ($this->user()?->hasRole('receptionist')) {
            return array_values(array_diff(self::STUDENT_KEYS, ['role']));
        }

        if ($this->input('role') === 'instructor') {
            return array_merge(self::STUDENT_KEYS, self::INSTRUCTOR_KEYS);
        }

        return self::STUDENT_KEYS;
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
