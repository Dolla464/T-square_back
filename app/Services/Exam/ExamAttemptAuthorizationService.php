<?php

namespace App\Services\Exam;

use App\DTO\AuthorizationResult;
use App\Models\ExamAttempt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExamAttemptAuthorizationService
{
    public function checkSubmittable(int $attemptId, int $studentId): AuthorizationResult
    {
        return DB::transaction(function () use ($attemptId, $studentId) {
            $attempt = ExamAttempt::query()
                ->with('exam')
                ->whereKey($attemptId)
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                return AuthorizationResult::notFound('Exam attempt not found.');
            }

            if ($attempt->student_id !== $studentId) {
                return AuthorizationResult::forbidden('You do not own this attempt.');
            }

            if ($attempt->status !== 'ongoing') {
                return AuthorizationResult::unprocessable('This attempt is no longer active.');
            }

            $exam = $attempt->exam;

            if (! $exam) {
                return AuthorizationResult::notFound('Exam not found.');
            }

            if (! $exam->is_active) {
                return AuthorizationResult::unprocessable('This exam is not currently available.');
            }

            return AuthorizationResult::allowed();
        });
    }

    public function isTimedOut(ExamAttempt $attempt): bool
    {
        return $this->isDurationExpired($attempt);
    }

    public function isEffectivelyOngoing(ExamAttempt $attempt): bool
    {
        return $attempt->status === ExamAttempt::STATUS_ONGOING && ! $this->isTimedOut($attempt);
    }

    public function getAttemptDurationMinutes(ExamAttempt $attempt): int
    {
        if ($attempt->duration_minutes !== null) {
            return (int) $attempt->duration_minutes;
        }

        return (int) ($attempt->exam?->duration ?? 0);
    }

    public function getDeadline(ExamAttempt $attempt): ?Carbon
    {
        $duration = $this->getAttemptDurationMinutes($attempt);

        if ($duration <= 0 || ! $attempt->started_at) {
            return null;
        }

        return Carbon::parse($attempt->started_at)->addMinutes($duration);
    }

    public function getRemainingSeconds(ExamAttempt $attempt): ?int
    {
        $deadline = $this->getDeadline($attempt);

        if (! $deadline) {
            return null;
        }

        return max(0, $deadline->getTimestamp() - Carbon::now()->getTimestamp());
    }

    /**
     * @return array{remaining_seconds: ?int, deadline_at: ?string, server_time: string, is_timed_out: bool}
     */
    public function getTimeStatusPayload(ExamAttempt $attempt): array
    {
        $deadline = $this->getDeadline($attempt);

        return [
            'remaining_seconds' => $this->getRemainingSeconds($attempt),
            'deadline_at' => $deadline?->toIso8601String(),
            'server_time' => Carbon::now()->toIso8601String(),
            'is_timed_out' => $this->isTimedOut($attempt),
        ];
    }

    private function isDurationExpired(ExamAttempt $attempt): bool
    {
        $duration = $this->getAttemptDurationMinutes($attempt);

        if ($duration <= 0 || ! $attempt->started_at) {
            return false;
        }

        $deadline = Carbon::parse($attempt->started_at)->addMinutes($duration);

        return Carbon::now()->greaterThanOrEqualTo($deadline);
    }
}
