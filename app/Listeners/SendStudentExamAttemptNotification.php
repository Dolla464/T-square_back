<?php

namespace App\Listeners;

use App\Events\StudentExamAttemptCompleted;
use App\Notifications\StudentExamAttemptStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendStudentExamAttemptNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(StudentExamAttemptCompleted $event): void
    {
        $attempt = $event->attempt;
        $studentUser = $attempt->student?->user;

        if (! $studentUser) {
            return;
        }

        $studentUser->notify(new StudentExamAttemptStatusNotification($attempt));
    }
}
