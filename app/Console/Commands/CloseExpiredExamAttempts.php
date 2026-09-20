<?php

namespace App\Console\Commands;

use App\Models\ExamAttempt;
use App\Services\Exam\ExamAttemptAuthorizationService;
use App\Services\User\ExamService;
use Illuminate\Console\Command;

class CloseExpiredExamAttempts extends Command
{
    protected $signature = 'exams:close-expired';

    protected $description = 'Auto-complete exam attempts that exceeded their allowed duration.';

    public function handle(
        ExamAttemptAuthorizationService $authorizationService,
        ExamService $examService,
    ): int {
        $closed = 0;

        ExamAttempt::query()
            ->where('status', ExamAttempt::STATUS_ONGOING)
            ->with('exam')
            ->chunkById(100, function ($attempts) use ($authorizationService, $examService, &$closed) {
                foreach ($attempts as $attempt) {
                    if (! $authorizationService->isTimedOut($attempt)) {
                        continue;
                    }

                    $examService->completeAttempt($attempt->id);
                    $closed++;
                }
            });

        $this->info("Closed {$closed} expired exam attempt(s).");

        return self::SUCCESS;
    }
}
