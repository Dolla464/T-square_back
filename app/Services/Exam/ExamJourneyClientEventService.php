<?php

namespace App\Services\Exam;

use App\Models\ExamAttempt;
use App\Models\Student;
use App\Support\StudentExamJourneyLogger;
use Illuminate\Support\Str;

class ExamJourneyClientEventService
{
    public function __construct(
        private StudentExamJourneyLogger $journeyLogger,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $events
     */
    public function recordEvents(ExamAttempt $attempt, Student $student, array $events): int
    {
        if (! $this->journeyLogger->clientEventsEnabled()) {
            return 0;
        }

        if ($attempt->student_id !== $student->id) {
            return 0;
        }

        $allowed = config('exam_journey.client_event_types', []);
        $sanitized = [];

        foreach ($events as $event) {
            $type = $event['type'] ?? null;

            if (! is_string($type) || ! in_array($type, $allowed, true)) {
                continue;
            }

            $sanitized[] = array_filter([
                'event_id' => isset($event['event_id']) && is_string($event['event_id'])
                    ? $event['event_id']
                    : (string) Str::uuid(),
                'type' => $type,
                'client_at' => $event['client_at'] ?? null,
                'metadata' => is_array($event['metadata'] ?? null) ? $event['metadata'] : null,
            ], fn ($value) => $value !== null);
        }

        if ($sanitized === []) {
            return 0;
        }

        $this->journeyLogger->logFromAttempt(
            StudentExamJourneyLogger::EVENT_CLIENT_EVENTS_BATCH,
            $attempt,
            [
                'client_event_count' => count($sanitized),
                'client_events' => $sanitized,
            ],
            'client',
        );

        return count($sanitized);
    }
}
