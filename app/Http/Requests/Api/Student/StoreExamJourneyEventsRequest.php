<?php

namespace App\Http\Requests\Api\Student;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamJourneyEventsRequest extends FormRequest
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
        $maxEvents = (int) config('exam_journey.max_events_per_request', 20);
        $allowedTypes = config('exam_journey.client_event_types', []);

        return [
            'events' => ['required', 'array', 'min:1', 'max:'.$maxEvents],
            'events.*.event_id' => ['required', 'uuid'],
            'events.*.type' => ['required', 'string', Rule::in($allowedTypes)],
            'events.*.client_at' => ['nullable', 'date'],
            'events.*.metadata' => ['nullable', 'array', 'max:10'],
            'events.*.metadata.*' => ['nullable', 'string', 'max:256'],
            'events.*.student_id' => ['prohibited'],
            'events.*.is_passed' => ['prohibited'],
            'events.*.status' => ['prohibited'],
            'student_id' => ['prohibited'],
        ];
    }
}
