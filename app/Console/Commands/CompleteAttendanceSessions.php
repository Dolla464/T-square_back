<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Services\Attendance\AttendanceSessionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompleteAttendanceSessions extends Command
{
    protected $signature   = 'attendance:complete';
    protected $description = 'Complete active attendance sessions 30 minutes after their scheduled end time and mark absent students.';

    public function handle(AttendanceSessionService $attendanceSessionService): void
    {
        $now       = Carbon::now();
        $threshold = $now->copy()->subMinutes(30);
        $today     = $now->toDateString();

        $this->line('=== attendance:complete diagnostic ===');
        $this->line('Timezone      : ' . config('app.timezone'));
        $this->line('Now           : ' . $now->toDateTimeString());
        $this->line('Today (date)  : ' . $today);
        $this->line('Threshold     : effective end_time must be <= ' . $threshold->format('H:i:s') . ' (now - 30 min)');
        $this->line('');

        $allActive = AttendanceSession::where('status', 'active')
            ->with('schedule')
            ->get()
            ->filter(function (AttendanceSession $session) use ($attendanceSessionService, $today) {
                $range = $attendanceSessionService->getEffectiveDateTimeRange($session);

                return $range['session_date'] === $today;
            });

        $this->line("Active sessions for today: {$allActive->count()}");

        if ($allActive->isEmpty()) {
            $this->warn('  → No active sessions exist for today. Run attendance:activate first.');
        }

        foreach ($allActive as $session) {
            $range      = $attendanceSessionService->getEffectiveDateTimeRange($session);
            $effectiveEnd = $range['end']->format('H:i:s');
            $pastThresh = $effectiveEnd <= $threshold->format('H:i:s');
            $label      = $pastThresh
                ? '✓ WILL COMPLETE'
                : "✗ too early — end_time={$effectiveEnd} must be <= {$threshold->format('H:i:s')}";

            $this->line("  Session #{$session->id} | start={$range['start']->format('H:i:s')} | end={$effectiveEnd} | {$label}");
        }

        $this->line('');

        $sessions = AttendanceSession::where('status', 'active')
            ->with(['schedule', 'attendanceRecords'])
            ->get()
            ->filter(function (AttendanceSession $session) use ($attendanceSessionService, $today, $threshold) {
                $range = $attendanceSessionService->getEffectiveDateTimeRange($session);

                if ($range['session_date'] !== $today) {
                    return false;
                }

                return $range['end']->format('H:i:s') <= $threshold->format('H:i:s');
            });

        $this->line("Sessions matched for completion: {$sessions->count()}");

        $completed = 0;

        foreach ($sessions as $session) {
            $this->line("  Completing session #{$session->id} and marking absent students...");

            $presentStudentIds = $session->attendanceRecords
                ->whereIn('status', ['present', 'late'])
                ->pluck('student_id')
                ->toArray();

            $enrolledStudentIds = DB::table('enrollments')
                ->where('group_id', $session->learning_group_id)
                ->pluck('student_id')
                ->toArray();

            $absentStudentIds = array_diff($enrolledStudentIds, $presentStudentIds);

            $this->line('    Enrolled: ' . count($enrolledStudentIds) . ' | Present: ' . count($presentStudentIds) . ' | Absent: ' . count($absentStudentIds));

            foreach ($absentStudentIds as $studentId) {
                AttendanceRecord::updateOrCreate(
                    ['session_id' => $session->id, 'student_id' => $studentId],
                    [
                        'student_qr_code' => 'auto_absent_' . $session->id . '_' . $studentId,
                        'status'          => 'absent',
                        'marked_by'       => 'system',
                        'marked_at'       => $now,
                        'qr_expires_at'   => $now,
                    ]
                );
            }

            $session->update(['status' => 'completed']);
            $completed++;
        }

        $this->info("Completed {$completed} attendance session(s) and marked absent students.");

        Log::info('attendance:complete', [
            'timezone'  => config('app.timezone'),
            'now'       => $now->toDateTimeString(),
            'threshold' => $threshold->format('H:i:s'),
            'active'    => $allActive->count(),
            'completed' => $completed,
        ]);
    }
}
