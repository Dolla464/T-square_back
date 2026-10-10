<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Notifications\Notification;

class StudentEnrollmentPendingNotification extends Notification
{
    public function __construct(
        public readonly Course $course,
        public readonly Enrollment $enrollment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->enrollment->loadMissing('order');

        return [
            'type' => 'enrollment_pending',
            'title' => 'Enrollment Pending',
            'message' => 'Your enrollment in "'.$this->course->title.'" is pending approval and payment.',
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'enrollment_id' => $this->enrollment->id,
            'order_id' => $this->enrollment->order_id,
            'status' => 'pending_payment',
            'icon' => 'hourglass',
        ];
    }
}
