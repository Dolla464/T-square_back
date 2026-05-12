<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentEnrolledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Course $course,
        public readonly Enrollment $enrollment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Enrollment Confirmed: '.$this->course->title)
            ->greeting('Hello '.($notifiable->name ?? 'Student').',')
            ->line('Your enrollment has been confirmed successfully.')
            ->line('Course: '.$this->course->title)
            ->action('Go to Course', url('/courses/'.$this->course->id))
            ->line('Thank you for learning with us.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Enrollment Confirmed',
            'message' => 'You have successfully enrolled in "'.$this->course->title.'".',
            'course_id' => $this->course->id,
            'enrollment_id' => $this->enrollment->id,
            'url' => '/courses/'.$this->course->id,
            'icon' => 'book-open',
        ];
    }
}
