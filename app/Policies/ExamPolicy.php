<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function view(User $user, Exam $exam): bool
    {
        if ($user->hasAnyRole(['admin', 'instructor'])) {
            return true;
        }

        return $user->student !== null;
    }

    public function manage(User $user, Exam $exam): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $instructorId = $user->instructor?->id;

        return $instructorId
            && $exam->course
            && $exam->course->hasInstructor($instructorId);
    }
}
