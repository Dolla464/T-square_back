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
        $this->attempt->loadMissing('exam.course');

        $isPassed = $this->attempt->status === 'passed';
        $examTitle = $this->attempt->exam?->title ?? 'Exam';
        $courseTitle = $this->attempt->exam?->course?->title ?? 'Course';
        $result = $isPassed ? 'passed' : 'failed';

        return [
            'type' => 'exam_result',
            'title' => 'Exam Attempt Result',
            'message' => "You {$result} the exam \"{$examTitle}\" in course \"{$courseTitle}\".",
            'course_id' => $this->attempt->exam?->course_id,
            'course_title' => $courseTitle,
            'exam_id' => $this->attempt->exam_id,
            'exam_title' => $examTitle,
            'attempt_id' => $this->attempt->id,
            'status' => $this->attempt->status,
            'score' => $this->attempt->score,
            'icon' => $isPassed ? 'patch-check' : 'x-circle',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
