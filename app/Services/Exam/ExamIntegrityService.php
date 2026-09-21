<?php

namespace App\Services\Exam;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptIntegrityEvent;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExamIntegrityService
{
    public const MAX_EVENTS_PER_REQUEST = 20;

    public const CLIENT_AT_FUTURE_TOLERANCE_SECONDS = 60;

    public function __construct(
        private ExamAttemptAuthorizationService $attemptAuthorizationService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function recordEvents(ExamAttempt $attempt, Student $student, array $events): int
    {
        $this->validateAttemptForRecording($attempt, $student);

        $inserted = 0;

        foreach ($events as $event) {
            $model = ExamAttemptIntegrityEvent::firstOrCreate(
                [
                    'exam_attempt_id' => $attempt->id,
                    'event_id' => $event['event_id'],
                ],
                [
                    'event_type' => $event['type'],
                    'client_at' => $this->sanitizeClientAt($event['client_at'] ?? null),
                    'occurred_at' => now(),
                    'metadata' => $event['metadata'] ?? null,
                ],
            );

            if ($model->wasRecentlyCreated) {
                $inserted++;
            }
        }

        return $inserted;
    }

    public function validateAttemptForRecording(ExamAttempt $attempt, Student $student): void
    {
        if ($attempt->student_id !== $student->id) {
            abort(403, 'You are not allowed to record events for this attempt.');
        }

        if ($attempt->status !== ExamAttempt::STATUS_ONGOING) {
            abort(422, 'This exam attempt is not ongoing.');
        }

        if ($this->attemptAuthorizationService->isTimedOut($attempt)) {
            abort(422, 'Exam time has expired.');
        }
    }

    public function sanitizeClientAt(mixed $clientAt): ?Carbon
    {
        if ($clientAt === null || $clientAt === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($clientAt);
        } catch (\Throwable) {
            return null;
        }

        if ($parsed->greaterThan(now()->addSeconds(self::CLIENT_AT_FUTURE_TOLERANCE_SECONDS))) {
            return null;
        }

        return $parsed;
    }

    /**
     * @return array{
     *     tab_hidden_count: int,
     *     total_hidden_duration_seconds: int,
     *     window_blur_count: int,
     *     window_resized_count: int,
     *     total_events: int
     * }
     */
    public function buildSummary(Collection $events): array
    {
        $ordered = $events->sortBy([
            ['occurred_at', 'asc'],
            ['id', 'asc'],
        ])->values();

        $tabHiddenCount = 0;
        $windowBlurCount = 0;
        $windowResizedCount = 0;
        $totalHiddenDuration = 0;
        $lastHiddenAt = null;

        foreach ($ordered as $event) {
            switch ($event->event_type) {
                case ExamAttemptIntegrityEvent::TYPE_TAB_HIDDEN:
                    $tabHiddenCount++;
                    $lastHiddenAt = $event->occurred_at;
                    break;
                case ExamAttemptIntegrityEvent::TYPE_TAB_VISIBLE:
                    if ($lastHiddenAt !== null && $event->occurred_at !== null) {
                        $totalHiddenDuration += max(0, $lastHiddenAt->diffInSeconds($event->occurred_at));
                        $lastHiddenAt = null;
                    }
                    break;
                case ExamAttemptIntegrityEvent::TYPE_WINDOW_BLUR:
                    $windowBlurCount++;
                    break;
                case ExamAttemptIntegrityEvent::TYPE_WINDOW_RESIZED:
                    $windowResizedCount++;
                    break;
            }
        }

        return [
            'tab_hidden_count' => $tabHiddenCount,
            'total_hidden_duration_seconds' => $totalHiddenDuration,
            'window_blur_count' => $windowBlurCount,
            'window_resized_count' => $windowResizedCount,
            'total_events' => $ordered->count(),
        ];
    }
}
