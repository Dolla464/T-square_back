<?php

namespace App\Notifications;

use App\Models\ExamAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InstructorGradingRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ExamAttempt $attempt,
        public readonly ?string $groupName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $studentName = $this->attempt->student?->full_name ?? 'A student';
        $examTitle = $this->attempt->exam?->title ?? 'Exam';
        $groupPart = $this->groupName ? " in group \"{$this->groupName}\"" : '';

        return [
            'type' => 'grading_required',
            'title' => 'Exam Grading Required',
            'message' => "{$studentName} submitted \"{$examTitle}\"{$groupPart}. Essay answers are awaiting your grading.",
            'student_id' => $this->attempt->student_id,
            'student_name' => $studentName,
            'exam_id' => $this->attempt->exam_id,
            'exam_title' => $examTitle,
            'attempt_id' => $this->attempt->id,
            'group_name' => $this->groupName,
            'status' => $this->attempt->status,
            'icon' => 'pencil-square',
        ];
    }
}
