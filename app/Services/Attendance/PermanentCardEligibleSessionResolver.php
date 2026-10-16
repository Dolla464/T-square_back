<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PermanentCardEligibleSessionResolver
{
    public function __construct(
        private AttendanceSessionService $attendanceSessionService,
        private AttendanceDeviceGroupAccessService $deviceGroupAccess,
    ) {}

    /**
     * @return Collection<int, AttendanceSession>
     */
    public function resolveEligibleSessions(
        Student $student,
        AttendanceDevice $device,
        ?Carbon $now = null
    ): Collection {
        $now = $now ?? Carbon::now();
        $today = $now->toDateString();

        $enrolledGroupIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->whereNotNull('group_id')
            ->pluck('group_id');

        if ($enrolledGroupIds->isEmpty()) {
            return collect();
        }

        $sessions = AttendanceSession::query()
            ->where('status', 'active')
            ->whereIn('learning_group_id', $enrolledGroupIds)
            ->where(function ($query) use ($today) {
                $query->whereDate('session_date', $today)
                    ->orWhereDate('override_date', $today);
            })
            ->with(['schedule', 'learningGroup'])
            ->get()
            ->filter(function (AttendanceSession $session) use ($student, $device, $now, $today) {
                $range = $this->attendanceSessionService->getEffectiveDateTimeRange($session);

                if ($range['session_date'] !== $today) {
                    return false;
                }

                $windowStart = $range['start']->copy()->subMinutes(30);
                $windowEnd = $range['end']->copy()->addMinutes(30);

                if ($now->lt($windowStart) || $now->gt($windowEnd)) {
                    return false;
                }

                $enrolledInSessionGroup = Enrollment::query()
                    ->where('student_id', $student->id)
                    ->where('group_id', $session->learning_group_id)
                    ->exists();

                if (! $enrolledInSessionGroup) {
                    return false;
                }

                $group = $session->learningGroup;

                if ($group === null) {
                    return false;
                }

                return $this->deviceGroupAccess->deviceCanAccessGroup($device, $group);
            })
            ->values();

        return $sessions;
    }
}
