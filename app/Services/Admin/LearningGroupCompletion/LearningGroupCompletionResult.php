<?php

namespace App\Services\Admin\LearningGroupCompletion;

readonly class LearningGroupCompletionResult
{
    /**
     * @param  array<int, SingleGroupCompletionResult>  $groupResults
     * @param  array<int, array{group_id: int, message: string}>  $failures
     */
    public function __construct(
        public int $scanned = 0,
        public int $completed = 0,
        public int $skippedAlreadyCompleted = 0,
        public int $skippedIneligible = 0,
        public int $enrollmentsCompleted = 0,
        public int $notificationsSent = 0,
        public int $failed = 0,
        public array $failures = [],
        public array $groupResults = [],
    ) {}

    public function completedGroupIds(): array
    {
        return array_values(array_map(
            fn (SingleGroupCompletionResult $result) => $result->groupId,
            array_filter(
                $this->groupResults,
                fn (SingleGroupCompletionResult $result) => $result->status === 'completed'
            )
        ));
    }

    public function failedGroupIds(): array
    {
        return array_values(array_map(
            fn (SingleGroupCompletionResult $result) => $result->groupId,
            array_filter(
                $this->groupResults,
                fn (SingleGroupCompletionResult $result) => $result->status === 'failed'
            )
        ));
    }
}
