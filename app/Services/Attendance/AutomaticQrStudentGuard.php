<?php

namespace App\Services\Attendance;

use App\Models\Student;

class AutomaticQrStudentGuard
{
    public function isEligibleForAutomaticCheckIn(Student $student): bool
    {
        $status = $student->getAttribute('status') ?? $student->getRawOriginal('status') ?? 'active';

        return $status === 'active';
    }
}
