<?php

namespace App\Notifications;

use App\Models\ExamAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InstructorExamResultNotification extends Notification implements ShouldQueue
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
        $isPassed    = $this->attempt->status === 'passed';
        $studentName = $this->attempt->student?->full_name ?? 'A student';
        $examTitle   = $this->attempt->exam?->title ?? 'Exam';
        $score       = $this->attempt->score;
        $result      = $isPassed ? 'passed' : 'failed';
        $groupPart   = $this->groupName ? " in group \"{$this->groupName}\"" : '';

        return [
            'type'         => 'instructor_exam_result',
            'title'        => 'Student Exam Result',
            'message'      => "{$studentName} {$result} the exam \"{$examTitle}\"{$groupPart}. Score: {$score}.",
            'student_id'   => $this->attempt->student_id,
            'student_name' => $studentName,
            'exam_id'      => $this->attempt->exam_id,
            'exam_title'   => $examTitle,
            'attempt_id'   => $this->attempt->id,
            'group_name'   => $this->groupName,
            'status'       => $this->attempt->status,
            'score'        => $score,
            'action_url'   => '/instructor/student-results',
            'icon'         => $isPassed ? 'patch-check' : 'x-circle',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
