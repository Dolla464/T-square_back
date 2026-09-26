<?php

namespace App\Console\Commands;

use App\Services\Admin\LearningGroupCompletionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class CompleteExpiredLearningGroups extends Command
{
    protected $signature = 'learning-groups:complete-expired
                            {--dry-run : List eligible groups without making changes}
                            {--date= : Override as-of date (YYYY-MM-DD) for eligibility checks}';

    protected $description = 'Complete active learning groups whose end_date is before today and sync enrollments.';

    public function handle(LearningGroupCompletionService $completionService): int
    {
        try {
            $asOfDate = $this->resolveAsOfDate();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run — no changes will be made.');
        }

        $this->info('Expired learning groups completion started.');
        $this->line('As-of date: '.$asOfDate->toDateString());

        $result = $completionService->completeExpiredGroups($asOfDate, $dryRun);

        if ($dryRun) {
            $eligible = $completionService->findEligibleGroups($asOfDate);

            $this->info('Eligible groups: '.$eligible->count());

            foreach ($eligible as $group) {
                $this->line(sprintf(
                    '  [%d] %s — course_id=%d end_date=%s',
                    $group->id,
                    $group->group_name,
                    $group->course_id,
                    $group->end_date?->toDateString() ?? 'null',
                ));
            }

            $this->info('Would complete '.$eligible->count().' group(s) (dry-run, no changes).');

            return self::SUCCESS;
        }

        $skipped = $result->skippedAlreadyCompleted + $result->skippedIneligible;

        $this->table(
            ['Metric', 'Count'],
            [
                ['Scanned', $result->scanned],
                ['Completed', $result->completed],
                ['Skipped', $skipped],
                ['Enrollments completed', $result->enrollmentsCompleted],
                ['Notifications sent', $result->notificationsSent],
                ['Failed', $result->failed],
            ]
        );

        if ($result->completedGroupIds() !== []) {
            $this->info('Completed group IDs: '.implode(', ', $result->completedGroupIds()));
        }

        if ($result->failedGroupIds() !== []) {
            $this->warn('Failed group IDs: '.implode(', ', $result->failedGroupIds()));
        }

        foreach ($result->failures as $failure) {
            $this->warn(sprintf(
                'Group %d failed: %s',
                $failure['group_id'],
                $failure['message'],
            ));
        }

        return $result->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveAsOfDate(): Carbon
    {
        $dateOption = $this->option('date');

        if ($dateOption === null || $dateOption === '') {
            return Carbon::today();
        }

        if (! is_string($dateOption) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOption)) {
            throw new \InvalidArgumentException('Invalid --date value. Expected format: YYYY-MM-DD.');
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $dateOption);

        if ($parsed === false || $parsed->format('Y-m-d') !== $dateOption) {
            throw new \InvalidArgumentException('Invalid --date value. Expected format: YYYY-MM-DD.');
        }

        return $parsed->startOfDay();
    }
}
