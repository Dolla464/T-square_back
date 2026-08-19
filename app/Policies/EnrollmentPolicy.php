<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function view(User $user, Enrollment $enrollment): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('receptionist')) {
            return true;
        }

        return $user->student?->id === $enrollment->student_id;
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->hasRole('admin') || $user->hasRole('receptionist');
    }
}
