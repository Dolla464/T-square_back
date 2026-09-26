<?php

namespace App\Services\Admin;

use App\Models\LearningGroup;
use App\Services\Admin\LearningGroupCompletion\LearningGroupCompletionResult;
use App\Services\Admin\LearningGroupCompletion\SingleGroupCompletionResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LearningGroupCompletionService
{
    public function __construct(
        private readonly AdminLearningGroupService $adminLearningGroupService,
    ) {}

    public function completeExpiredGroups(?Carbon $asOfDate = null, bool $dryRun = false): LearningGroupCompletionResult
    {
        $asOfDate ??= Carbon::today();
        $eligibleGroups = $this->findEligibleGroups($asOfDate);

        if ($dryRun) {
            return new LearningGroupCompletionResult(
                scanned: $eligibleGroups->count(),
                groupResults: $eligibleGroups
                    ->map(fn (LearningGroup $group) => new SingleGroupCompletionResult(
                        groupId: $group->id,
                        status: 'skipped',
                        oldStatus: $group->status,
                    ))
                    ->values()
                    ->all(),
            );
        }

        $groupResults = [];
        $failures = [];
        $completed = 0;
        $skippedIneligible = 0;
        $enrollmentsCompleted = 0;
        $notificationsSent = 0;
        $failed = 0;

        foreach ($eligibleGroups as $group) {
            try {
                $singleResult = $this->completeGroupIfExpired($group, $asOfDate);
                $groupResults[] = $singleResult;

                match ($singleResult->status) {
                    'completed' => $completed++,
                    'skipped' => $skippedIneligible++,
                    'failed' => $failed++,
                    default => null,
                };

                $enrollmentsCompleted += $singleResult->enrollmentsCompleted;
                $notificationsSent += $singleResult->notificationsSent;
            } catch (Throwable $e) {
                $failed++;
                $failures[] = [
                    'group_id' => $group->id,
                    'message' => $e->getMessage(),
                ];
                $groupResults[] = new SingleGroupCompletionResult(
                    groupId: $group->id,
                    status: 'failed',
                    oldStatus: $group->status,
                    error: $e->getMessage(),
                );

                Log::error('learning-groups:complete-expired failed for group', [
                    'group_id' => $group->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return new LearningGroupCompletionResult(
            scanned: $eligibleGroups->count(),
            completed: $completed,
            skippedIneligible: $skippedIneligible,
            enrollmentsCompleted: $enrollmentsCompleted,
            notificationsSent: $notificationsSent,
            failed: $failed,
            failures: $failures,
            groupResults: $groupResults,
        );
    }

    public function completeGroupIfExpired(
        LearningGroup $group,
        ?Carbon $asOfDate = null,
    ): SingleGroupCompletionResult {
        $asOfDate ??= Carbon::today();
        $oldStatus = $group->status;

        if (! $this->isEligible($group, $asOfDate)) {
            return new SingleGroupCompletionResult(
                groupId: $group->id,
                status: 'skipped',
                oldStatus: $oldStatus,
            );
        }

        $syncResult = DB::transaction(function () use ($group, $asOfDate) {
            $lockedGroup = LearningGroup::query()
                ->whereKey($group->id)
                ->lockForUpdate()
                ->first();

            if ($lockedGroup === null || ! $this->isEligible($lockedGroup, $asOfDate)) {
                return null;
            }

            $lockedGroup->update(['status' => 'completed']);

            return $this->adminLearningGroupService->syncEnrollmentsWithGroupStatus(
                $lockedGroup->fresh(),
                'active',
                'completed',
                sendNotifications: false,
            );
        });

        if ($syncResult === null) {
            return new SingleGroupCompletionResult(
                groupId: $group->id,
                status: 'skipped',
                oldStatus: $oldStatus,
            );
        }

        $notificationsSent = 0;
        $newlyCompleted = $syncResult['newly_completed_enrollments'] ?? collect();

        if ($newlyCompleted->isNotEmpty()) {
            $freshGroup = LearningGroup::query()->findOrFail($group->id);
            $notificationsSent = $this->adminLearningGroupService->sendReviewNotificationsForNewlyCompleted(
                $freshGroup,
                $newlyCompleted,
            );
        }

        return new SingleGroupCompletionResult(
            groupId: $group->id,
            status: 'completed',
            oldStatus: $oldStatus,
            enrollmentsCompleted: (int) ($syncResult['enrollments_completed'] ?? 0),
            notificationsSent: $notificationsSent,
        );
    }

    /**
     * @return Collection<int, LearningGroup>
     */
    public function findEligibleGroups(Carbon $asOfDate): Collection
    {
        return LearningGroup::query()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $asOfDate->toDateString())
            ->orderBy('end_date')
            ->orderBy('id')
            ->get();
    }

    private function isEligible(LearningGroup $group, Carbon $asOfDate): bool
    {
        if ($group->status !== 'active') {
            return false;
        }

        if ($group->end_date === null) {
            return false;
        }

        return $group->end_date->lt($asOfDate);
    }
}
