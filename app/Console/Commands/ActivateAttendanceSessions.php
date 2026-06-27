<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Notifications\SessionActivated;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ActivateAttendanceSessions extends Command
{
    protected $signature   = 'attendance:activate';
    protected $description = 'Activate upcoming attendance sessions within the activation window.';

    public function handle(): void
    {
        $now         = Carbon::now();
        $windowStart = $now->copy()->subMinutes(5);
        $windowEnd   = $now->copy()->addMinutes(30);

        $this->line('=== attendance:activate ===');
        $this->line("Now: {$now->toDateTimeString()} | Window: {$windowStart->format('H:i:s')} → {$windowEnd->format('H:i:s')}");

        $sessions = AttendanceSession::where('status', 'upcoming')
            ->whereDate('session_date', $now->toDateString())
            ->whereHas('schedule', function ($q) use ($windowStart, $windowEnd) {
                $q->whereTime('start_time', '>=', $windowStart->format('H:i:s'))
                  ->whereTime('start_time', '<=', $windowEnd->format('H:i:s'));
            })
            ->with('schedule')
            ->lockForUpdate()
            ->get();

        $activated = 0;

        foreach ($sessions as $session) {
            DB::transaction(function () use ($session, &$activated) {
                $qrCode = 'sess_' . Str::random(16);
                
                // Ensure uniqueness
                while (AttendanceSession::where('qr_code', $qrCode)->exists()) {
                    $qrCode = 'sess_' . Str::random(16);
                }

                $session->update([
                    'status'  => 'active',
                    'qr_code' => $qrCode,
                ]);

                // Notify instructor
                $instructor = $session->learningGroup?->instructor;
                if ($instructor?->user) {
                    $instructor->user->notify(new SessionActivated($session));
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