<?php

namespace App\Services\Attendance\DTO;

use App\Models\StudentAttendanceCard;

/**
 * Raw token is returned only from issuance/reissue operations for one-time display (e.g. printing).
 */
readonly class IssuedStudentAttendanceCard
{
    public function __construct(
        public StudentAttendanceCard $card,
        public string $plainTextToken,
    ) {}
}
