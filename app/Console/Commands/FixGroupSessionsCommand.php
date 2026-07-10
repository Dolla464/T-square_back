<?php

namespace App\Console\Commands;

use App\Models\LearningGroup;
use App\Services\Admin\AdminLearningGroupService;
use Illuminate\Console\Command;

class FixGroupSessionsCommand extends Command
{
    protected $signature = 'attendance:fix-group-sessions
                            {group? : The learning group ID}
                            {--all : Fix all learning groups}
                            {--dry-run : Preview changes without applying them}';

    protected $description = 'Correct group end_date from course duration and remove out-of-range upcoming sessions.';

    public function handle(AdminLearningGroupService $groupService): int
    {
        $groupId = $this->argument('group');
        $fixAll  = (bool) $this->option('all');
        $dryRun  = (bool) $this->option('dry-run');

        if (! $groupId && ! $fixAll) {
            $this->error('Provide a group ID or use --all.');

            return self::FAILURE;
        }

        if ($groupId && $fixAll) {
            $this->error('Use either a group ID or --all, not both.');

            return self::FAILURE;
        }

        $groups = $fixAll
            ? LearningGroup::query()->with('course:id,duration_weeks')->orderBy('id')->get()
            : LearningGroup::query()->with('course:id,duration_weeks')->whereKey($groupId)->get();

        if ($groups->isEmpty()) {
            $this->error('No learning group found.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $rows            = [];
        $updatedCount    = 0;
        $skippedCount    = 0;
        $unchangedCount  = 0;
        $endDatesFixed   = 0;
        $sessionsRemoved = 0;

        foreach ($groups as $group) {
            $result = $groupService->fixGroupSessionBounds($group, $dryRun);

            if ($result['skipped_reason']) {
                $skippedCount++;
                $rows[] = [
                    $result['group_id'],
                    $result['group_name'],
                    'skipped',
                    $result['skipped_reason'],
                    '-',
                    '-',
                    0,
                ];

                continue;
            }

            if (! $result['updated']) {
                $unchangedCount++;
                $rows[] = [
                    $result['group_id'],
                    $result['group_name'],
                    'ok',
                    $result['old_end_date'] ?? '—',
                    $result['new_end_date'] ?? '—',
                    'no',
                    0,
                ];

                continue;
            }

            $updatedCount++;
            $endDatesFixed   += $result['end_date_changed'] ? 1 : 0;
            $sessionsRemoved += $result['sessions_removed'];

            $rows[] = [
                $result['group_id'],
                $result['group_name'],
                $dryRun ? 'would fix' : 'fixed',
                $result['old_end_date'] ?? '—',
                $result['new_end_date'] ?? '—',
                $result['end_date_changed'] ? 'yes' : 'no',
                $result['sessions_removed'],
            ];
        }

        $this->table(
            ['ID', 'Group', 'Status', 'Old end', 'New end', 'End changed', 'Sessions removed'],
            $rows
        );

        $this->newLine();
        $this->info(sprintf(
            'Processed %d group(s): %d updated, %d unchanged, %d skipped.',
            $groups->count(),
            $updatedCount,
            $unchangedCount,
            $skippedCount
        ));
        $this->line("End dates corrected: {$endDatesFixed}");
        $this->line("Upcoming sessions removed: {$sessionsRemoved}");

        if ($dryRun && $updatedCount > 0) {
            $this->warn('Re-run without --dry-run to apply these changes.');
        }

        return self::SUCCESS;
    }
}
