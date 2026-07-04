<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Notifications\SessionActivated;
use App\Services\Attendance\AttendanceSessionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ActivateAttendanceSessions extends Command
{
    protected $signature   = 'attendance:activate';
    protected $description = 'Activate upcoming attendance sessions within the activation window.';

    public function handle(AttendanceSessionService $attendanceSessionService): void
    {
        $now         = Carbon::now();
        $windowStart = $now->copy()->subMinutes(5);
        $windowEnd   = $now->copy()->addMinutes(30);
        $today       = $now->toDateString();

        $this->line('=== attendance:activate ===');
        $this->line("Now: {$now->toDateTimeString()} | Window: {$windowStart->format('H:i:s')} → {$windowEnd->format('H:i:s')}");

        $sessions = AttendanceSession::where('status', 'upcoming')
            ->with('schedule')
            ->get()
            ->filter(function (AttendanceSession $session) use ($attendanceSessionService, $today, $windowStart, $windowEnd) {
                $range = $attendanceSessionService->getEffectiveDateTimeRange($session);

                if ($range['session_date'] !== $today) {
                    return false;
                }

                return $range['start']->between($windowStart, $windowEnd);
            });

        $activated = 0;

        foreach ($sessions as $session) {
            DB::transaction(function () use ($session, &$activated) {
                $qrCode = 'sess_' . Str::random(16);

                while (AttendanceSession::where('qr_code', $qrCode)->exists()) {
                    $qrCode = 'sess_' . Str::random(16);
                }

                $session->update([
                    'status'  => 'active',
                    'qr_code' => $qrCode,
                ]);

                $session->loadMissing(['learningGroup.instructor.user', 'learningGroup.course', 'schedule']);

                $instructor = $session->learningGroup?->instructor;
                if ($instructor?->user) {
                    $instructor->user->notify(new SessionActivated($session));
                }

                $group = $session->learningGroup;
                if ($group) {
                    $studentUserIds = DB::table('enrollments')
                        ->join('students', 'enrollments.student_id', '=', 'students.id')
                        ->join('users', 'students.user_id', '=', 'users.id')
                        ->where('enrollments.group_id', $group->id)
                        ->pluck('users.id');

                    if ($studentUserIds->isNotEmpty()) {
                        $students = User::whereIn('id', $studentUserIds)->get();
                        Notification::send($students, new SessionActivated($session));
                    }
                }

                $activated++;
            });
        }

        $this->info("Activated {$activated} session(s).");

        Log::info('attendance:activate', [
            'now'       => $now->toDateTimeString(),
            'activated' => $activated,
        ]);
    }
}
