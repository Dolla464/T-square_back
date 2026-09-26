<?php

namespace App\Services\Admin\LearningGroupCompletion;

readonly class SingleGroupCompletionResult
{
    public function __construct(
        public int $groupId,
        public string $status,
        public string $oldStatus,
        public int $enrollmentsCompleted = 0,
        public int $notificationsSent = 0,
        public ?string $error = null,
    ) {}
}
