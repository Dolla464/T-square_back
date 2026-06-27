<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompleteAttendanceSessions extends Command
{
    protected $signature   = 'attendance:complete';
    protected $description = 'Complete active attendance sessions 30 minutes after their scheduled end time and mark absent students.';

    public function handle(): void
    {
        $now       = Carbon::now();
        $threshold = $now->copy()->subMinutes(30);   // end_time must be <= this to trigger completion

        // ── Diagnostic header ─────────────────────────────────────────────────
        $this->line('=== attendance:complete diagnostic ===');
        $this->line('Timezone      : ' . config('app.timezone'));
        $this->line('Now           : ' . $now->toDateTimeString());
        $this->line('Today (date)  : ' . $now->toDateString());
        $this->line('Threshold     : end_time must be <= ' . $threshold->format('H:i:s') . ' (now - 30 min)');
        $this->line('');

        // ── Show ALL active sessions for today (ignore end_time check) ────────
        $allActive = AttendanceSession::where('status', 'active')
            ->whereDate('session_date', $now->toDateString())
            ->with('schedule')
            ->get();

        $this->line("Active sessions for today: {$allActive->count()}");

        if ($allActive->isEmpty()) {
            $this->warn('  → No active sessions exist for today. Run attendance:activate first.');
        }

        foreach ($allActive as $s) {
            $rawEnd     = $s->schedule ? $s->schedule->getRawOriginal('end_time') : 'NULL (no schedule)';
            $rawStart   = $s->schedule ? $s->schedule->getRawOriginal('start_time') : 'NULL';
            $pastThresh = $s->schedule && $rawEnd <= $threshold->format('H:i:s');
            $label      = $pastThresh
                ? '✓ WILL COMPLETE'
                : "✗ too early — end_time={$rawEnd} must be <= {$threshold->format('H:i:s')} (fires at " . (Carbon::createFromFormat('H:i:s', $rawEnd)->addMinutes(30)->format('H:i:s')) . ')';

            $this->line("  Session #{$s->id} | schedule_id={$s->schedule_id} | start={$rawStart} | end={$rawEnd} | {$label}");
        }

        $this->line('');

        // ── Main query: sessions whose end_time passed 30+ minutes ago ────────
        $sessions = AttendanceSession::where('status', 'active')
            ->whereDate('session_date', $now->toDateString())
            ->whereHas('schedule', function ($q) use ($threshold) {
                $q->whereTime('end_time', '<=', $threshold->format('H:i:s'));
            })
            ->with(['schedule', 'attendanceRecords'])
            ->get();

        $this->line("Sessions matched for completion: {$sessions->count()}");

        $completed = 0;

        foreach ($sessions as $session) {
            $this->line("  Completing session #{$session->id} and marking absent students...");

            $presentStudentIds = $session->attendanceRecords->pluck('student_id')->toArray();

            $enrolledStudentIds = DB::table('enrollments')
                ->where('group_id', $session->learning_group_id)
                ->pluck('student_id')
                ->toArray();

            $absentStudentIds = array_diff($enrolledStudentIds, $presentStudentIds);

            $this->line("    Enrolled: " . count($enrolledStudentIds) . " | Present: " . count($presentStudentIds) . " | Absent: " . count($absentStudentIds));

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
