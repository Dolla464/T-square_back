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

    private function isDurationExpired(ExamAttempt $attempt): bool
    {
        $duration = (int) ($attempt->exam?->duration ?? 0);

        if ($duration <= 0 || ! $attempt->started_at) {
            return false;
        }

        $deadline = Carbon::parse($attempt->started_at)->addMinutes($duration);

        return Carbon::now()->greaterThanOrEqualTo($deadline);
    }
}
