<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Models\LearningGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseReviewRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly LearningGroup $group,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->enrollment->loadMissing('course');
        $courseTitle = $this->enrollment->course?->title ?? 'your course';
        $reviewUrl = url('/student/review/'.$this->enrollment->course_id);

        return (new MailMessage)
            ->subject('Course Completed — Please Leave a Review')
            ->greeting('Hello '.($notifiable->name ?? 'Student').',')
            ->line('Your learning group "'.$this->group->group_name.'" has been marked as completed.')
            ->line('Course: '.$courseTitle)
            ->line('Please submit a course review to receive your certificate.')
            ->action('Leave a Review', $reviewUrl)
            ->line('Thank you for learning with us.');
    }

    public function toDatabase(object $notifiable): array
    {
        $this->enrollment->loadMissing('course');
        $courseTitle = $this->enrollment->course?->title ?? 'your course';

        return [
            'type' => 'course_review_required',
            'title' => 'Course Completed',
            'message' => 'Your group "'.$this->group->group_name.'" is complete. Leave a review for "'.$courseTitle.'" to get your certificate.',
            'course_id' => $this->enrollment->course_id,
            'enrollment_id' => $this->enrollment->id,
            'group_id' => $this->group->id,
            'action_url' => '/student/review/'.$this->enrollment->course_id,
            'icon' => 'star',
        ];
    }
}
