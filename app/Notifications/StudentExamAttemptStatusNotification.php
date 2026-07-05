<?php

namespace App\Notifications;

use App\Models\ExamAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StudentExamAttemptStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ExamAttempt $attempt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $isPassed = $this->attempt->status === 'passed';

        return [
            'type' => 'exam_result',
            'title' => 'Exam Attempt Result',
            'message' => $isPassed
                ? 'You passed your exam attempt.'
                : 'You failed your exam attempt.',
            'exam_id' => $this->attempt->exam_id,
            'attempt_id' => $this->attempt->id,
            'status' => $this->attempt->status,
            'score' => $this->attempt->score,
            'icon' => $isPassed ? 'patch-check' : 'x-circle',
        ];
    }
}
