<?php

namespace App\Console\Commands;

use App\Services\Enrollment\OnlineEnrollmentCompletionService;
use Illuminate\Console\Command;

class CompleteOnlineEnrollments extends Command
{
    protected $signature = 'enrollments:complete-online';

    protected $description = 'Complete eligible online enrollments whose course duration has elapsed.';

    public function handle(OnlineEnrollmentCompletionService $completionService): int
    {
        $this->info('Online enrollment auto-completion started.');

        $completedCount = $completionService->completeEligibleEnrollments();

        $this->info("Completed {$completedCount} online enrollment(s).");

        return self::SUCCESS;
    }
}
