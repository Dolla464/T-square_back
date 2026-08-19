<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    public function view(User $user, AttendanceRecord $record): bool
    {
        if ($user->hasAnyRole(['admin', 'instructor', 'receptionist'])) {
            return true;
        }

        return $user->student?->id === $record->student_id;
    }
}
