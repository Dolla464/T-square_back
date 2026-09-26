<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceSessionCompletionService;
use Illuminate\Console\Command;

class RepairStaleAttendanceSessions extends Command
{
    protected $signature = 'attendance:repair-stale';

    protected $description = 'Run session completion once for stale active/upcoming sessions (post-deploy repair).';

    public function handle(AttendanceSessionCompletionService $completionService): int
    {
        $completed = $completionService->completeEligibleSessions();

        $this->info("Repaired {$completed} stale attendance session(s).");

        return self::SUCCESS;
    }
}
