<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceSessionCompletionService
{
    public function __construct(
        private AttendanceSessionService $attendanceSessionService,
    ) {}

    public function completeEligibleSessions(?Carbon $now = null): int
    {
        $now = $now ?? Carbon::now();

        $sessions = AttendanceSession::query()
            ->whereIn('status', ['active', 'upcoming'])
            ->with(['schedule', 'attendanceRecords'])
            ->get()
            ->filter(fn (AttendanceSession $session) => $this->attendanceSessionService->hasSessionEnded($session, $now));

        $completed = 0;

        foreach ($sessions as $session) {
            $this->completeSession($session, $now);
            $completed++;
        }

        Log::info('attendance:complete-eligible', [
            'timezone' => config('app.timezone'),
            'now' => $now->toDateTimeString(),
            'completed' => $completed,
        ]);

        return $completed;
    }

    private function completeSession(AttendanceSession $session, Carbon $now): void
    {
        $presentStudentIds = $session->attendanceRecords
            ->whereIn('status', ['present', 'late'])
            ->pluck('student_id')
            ->toArray();

        $enrolledStudentIds = DB::table('enrollments')
            ->where('group_id', $session->learning_group_id)
            ->pluck('student_id')
            ->toArray();

        $absentStudentIds = array_diff($enrolledStudentIds, $presentStudentIds);

        foreach ($absentStudentIds as $studentId) {
            AttendanceRecord::updateOrCreate(
                ['session_id' => $session->id, 'student_id' => $studentId],
                [
                    'student_qr_code' => 'auto_absent_'.$session->id.'_'.$studentId,
                    'status' => 'absent',
                    'marked_by' => 'system',
                    'marked_at' => $now,
                    'qr_expires_at' => $now,
                ]
            );
        }

        $session->update(['status' => 'completed']);
    }
}
