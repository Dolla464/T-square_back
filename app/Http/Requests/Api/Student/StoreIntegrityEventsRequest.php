<?php

namespace App\Http\Requests\Api\Student;

use App\Models\ExamAttemptIntegrityEvent;
use App\Services\Exam\ExamIntegrityService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIntegrityEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->student !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:'.ExamIntegrityService::MAX_EVENTS_PER_REQUEST],
            'events.*.event_id' => ['required', 'uuid'],
            'events.*.type' => ['required', 'string', Rule::in(ExamAttemptIntegrityEvent::TYPES)],
            'events.*.client_at' => ['nullable', 'date'],
            'events.*.metadata' => ['nullable', 'array'],
            'events.*.occurred_at' => ['prohibited'],
            'events.*.student_id' => ['prohibited'],
            'events.*.user_id' => ['prohibited'],
            'occurred_at' => ['prohibited'],
            'student_id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }

}
