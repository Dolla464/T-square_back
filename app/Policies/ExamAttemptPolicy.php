<?php

namespace App\Policies;

use App\Models\ExamAttempt;
use App\Models\User;

class ExamAttemptPolicy
{
    public function view(User $user, ExamAttempt $attempt): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('instructor')) {
            return true;
        }

        return $user->student?->id === $attempt->student_id;
    }

    public function update(User $user, ExamAttempt $attempt): bool
    {
        return $user->student?->id === $attempt->student_id
            && $attempt->status === 'ongoing';
    }
}
