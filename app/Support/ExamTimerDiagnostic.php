<?php

namespace App\Support;

use App\Models\ExamAttempt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ExamTimerDiagnostic
{
    public static function enabled(): bool
    {
        return (bool) config('exam_timer.diagnostic', false);
    }

    public static function logStart(ExamAttempt $attempt, array $responseData): void
    {
        if (! self::enabled()) {
            return;
        }

        self::write('START', self::buildContext($attempt, $responseData));
    }

    public static function logTimeStatus(ExamAttempt $attempt, array $responseData): void
    {
        if (! self::enabled()) {
            return;
        }

        self::write('TIME_STATUS', self::buildContext($attempt, $responseData));
    }

    private static function write(string $event, array $context): void
    {
        Log::info("[EXAM_TIMER_DIAGNOSTIC][{$event}]", $context);
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildContext(ExamAttempt $attempt, array $responseData): array
    {
        $durationMinutes = self::resolveDurationMinutes($attempt, $responseData);
        $durationSeconds = $durationMinutes > 0 ? $durationMinutes * 60 : null;

        $startedAt = $responseData['started_at'] ?? $attempt->started_at?->toIso8601String();
        $deadlineAt = $responseData['deadline_at'] ?? null;
        $remainingSeconds = $responseData['remaining_seconds'] ?? null;

        $flags = self::buildSanityFlags(
            $durationMinutes,
            $durationSeconds,
            $startedAt,
            $deadlineAt,
            $remainingSeconds,
        );

        return array_merge([
            'attempt_id' => $responseData['attempt_id'] ?? $attempt->id,
            'duration_minutes' => $durationMinutes,
            'duration_seconds' => $durationSeconds,
            'started_at' => $startedAt,
            'deadline_at' => $deadlineAt,
            'remaining_seconds' => $remainingSeconds,
            'server_time' => $responseData['server_time'] ?? null,
            'status' => $responseData['status'] ?? $attempt->status,
            'is_timed_out' => $responseData['is_timed_out'] ?? null,
        ], $flags);
    }

    private static function resolveDurationMinutes(ExamAttempt $attempt, array $responseData): int
    {
        if ($attempt->duration_minutes !== null) {
            return (int) $attempt->duration_minutes;
        }

        if (isset($responseData['duration'])) {
            return (int) $responseData['duration'];
        }

        return (int) ($attempt->exam?->duration ?? 0);
    }

    /**
     * @return array<string, bool|null>
     */
    private static function buildSanityFlags(
        int $durationMinutes,
        ?int $durationSeconds,
        ?string $startedAt,
        ?string $deadlineAt,
        mixed $remainingSeconds,
    ): array {
        if ($durationMinutes <= 0 || $durationSeconds === null) {
            return [
                'remaining_exceeds_duration' => null,
                'deadline_duration_mismatch' => null,
            ];
        }

        $remainingExceeds = is_numeric($remainingSeconds)
            && (float) $remainingSeconds > $durationSeconds;

        $deadlineMismatch = false;

        if ($startedAt && $deadlineAt) {
            try {
                $started = Carbon::parse($startedAt);
                $deadline = Carbon::parse($deadlineAt);
                $actualDelta = $deadline->getTimestamp() - $started->getTimestamp();
                $deadlineMismatch = abs($actualDelta - $durationSeconds) > 1;
            } catch (\Throwable) {
                $deadlineMismatch = true;
            }
        }

        return [
            'remaining_exceeds_duration' => $remainingExceeds,
            'deadline_duration_mismatch' => $deadlineMismatch,
        ];
    }
}
