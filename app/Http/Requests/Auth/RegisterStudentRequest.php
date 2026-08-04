<?php

namespace App\Http\Requests\Auth;

use App\Support\SuspiciousRequestLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegisterStudentRequest extends FormRequest
{
    public const ALLOWED = [
        'full_name',
        'name',
        'email',
        'password',
        'phone',
        'gender',
        'avatar',
    ];

    private const FORBIDDEN_DETECTORS = [
        'role',
        'verified',
        'email_verified_at',
        'group_id',
        'status',
        'created_by',
        'bio',
        'field',
        'enrollment_number',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->ensureIsNotRateLimited();

        $extra = SuspiciousRequestLogger::detectExtraFields($this, self::ALLOWED);
        if ($extra !== []) {
            SuspiciousRequestLogger::log($this, 'forbidden_fields_on_register', $extra);
        }

        if ($this->full_name) {
            $words = explode(' ', trim((string) $this->full_name));
            $firstNameTwo = implode(' ', array_slice($words, 0, 2));

            $this->merge([
                'name' => $firstNameTwo,
                'email' => strtolower(trim((string) $this->email)),
            ]);
        }
    }

    public function rules(): array
    {
        $forbiddenRules = collect(self::FORBIDDEN_DETECTORS)
            ->mapWithKeys(fn (string $field) => [$field => ['prohibited']])
            ->all();

        return array_merge($forbiddenRules, [
            'full_name' => ['required', 'string', 'max:255', 'min:10'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'phone' => ['required', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ]);
    }

    public function safePayload(): array
    {
        return $this->safe()->only(self::ALLOWED);
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey());
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->hitRateLimiter();

        parent::failedValidation($validator);
    }
}
