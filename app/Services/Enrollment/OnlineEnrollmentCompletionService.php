<?php

namespace App\Services\Enrollment;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OnlineEnrollmentCompletionService
{
    public function completeEligibleEnrollments(): int
    {
        $completedCount = 0;

        Enrollment::query()
            ->where('attendance_type', Course::ATTENDANCE_TYPE_ONLINE)
            ->where('is_completed', false)
            ->whereHas('order', fn ($query) => $query->where('status', 'completed'))
            ->whereHas('course', fn ($query) => $query->where('duration_weeks', '>', 0))
            ->with(['order', 'course'])
            ->orderBy('id')
            ->chunkById(100, function ($enrollments) use (&$completedCount): void {
                foreach ($enrollments as $enrollment) {
                    if (! $this->isEligibleForAutoCompletion($enrollment)) {
                        continue;
                    }

                    try {
                        if ($enrollment->markAsCompleted()) {
                            $completedCount++;
                        }
                    } catch (ValidationException $exception) {
                        Log::warning('Skipped online enrollment auto-completion due to guard rejection.', [
                            'enrollment_id' => $enrollment->id,
                            'message' => $exception->getMessage(),
                        ]);
                    } catch (Throwable $exception) {
                        Log::warning('Skipped online enrollment auto-completion due to an unexpected error.', [
                            'enrollment_id' => $enrollment->id,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return $completedCount;
    }

    public function isEligibleForAutoCompletion(Enrollment $enrollment): bool
    {
        if ($enrollment->attendance_type !== Course::ATTENDANCE_TYPE_ONLINE || $enrollment->is_completed) {
            return false;
        }

        $order = $enrollment->order;
        $course = $enrollment->course;

        if ($order === null || $order->status !== 'completed' || $order->status_changed_at === null) {
            return false;
        }

        $durationWeeks = $course?->duration_weeks;

        if ($durationWeeks === null || $durationWeeks <= 0) {
            return false;
        }

        return now()->greaterThanOrEqualTo(
            $order->status_changed_at->copy()->addWeeks((int) $durationWeeks)
        );
    }
}
