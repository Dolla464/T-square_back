<?php

namespace App\Notifications;

use App\Models\ExamAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentExamAttemptStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ExamAttempt $attempt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $examTitle = $this->attempt->exam?->title ?? 'Exam';
        $courseTitle = $this->attempt->exam?->course?->title;
        $isPassed = $this->attempt->status === 'passed';

        $message = $isPassed
            ? 'Congratulations! You passed your exam attempt.'
            : 'Your exam attempt result is failed. Keep going and try again.';

        $mail = (new MailMessage)
            ->subject('Exam Attempt Result: '.($isPassed ? 'Passed' : 'Failed'))
            ->greeting('Hello '.($notifiable->name ?? 'Student').',')
            ->line($message)
            ->line('Exam: '.$examTitle)
            ->line('Status: '.strtoupper($this->attempt->status))
            ->line('Score: '.$this->attempt->score);

        if ($courseTitle) {
            $mail->line('Course: '.$courseTitle);
        }

        return $mail->action('View My Results', url('/exams/my-results'));
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
            'action_url' => '/student/quizzes',
            'icon' => $isPassed ? 'patch-check' : 'x-circle',
        ];
    }
}
