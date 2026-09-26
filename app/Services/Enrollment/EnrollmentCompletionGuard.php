<?php

namespace App\Services\Enrollment;

use App\Models\Enrollment;
use Illuminate\Validation\ValidationException;

class EnrollmentCompletionGuard
{
    public function assertCanComplete(Enrollment $enrollment): void
    {
        if ($this->canComplete($enrollment)) {
            return;
        }

        throw ValidationException::withMessages([
            'is_completed' => [$this->exceptionMessage($enrollment)],
        ]);
    }

    public function canComplete(Enrollment $enrollment): bool
    {
        if ($enrollment->is_completed) {
            return true;
        }

        if ($enrollment->group_id === null) {
            return false;
        }

        $group = $enrollment->relationLoaded('learningGroup')
            ? $enrollment->learningGroup
            : $enrollment->learningGroup()->first();

        if ($group === null) {
            return false;
        }

        return $group->status === 'completed';
    }

    public function exceptionMessage(Enrollment $enrollment): string
    {
        if ($enrollment->group_id === null) {
            return 'Cannot mark course completed until the student is assigned to a completed learning group.';
        }

        $group = $enrollment->relationLoaded('learningGroup')
            ? $enrollment->learningGroup
            : $enrollment->learningGroup()->first();

        if ($group === null) {
            return 'Cannot mark course completed because the assigned learning group could not be found.';
        }

        return 'Cannot mark course completed until the learning group is closed (status: completed).';
    }
}
