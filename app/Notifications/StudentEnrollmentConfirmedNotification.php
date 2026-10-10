<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Notifications\Notification;

class StudentEnrollmentConfirmedNotification extends Notification
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
        return [
            'type' => 'enrollment_confirmed',
            'title' => 'Enrollment Confirmed',
            'message' => 'You can now access "'.$this->course->title.'".',
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'enrollment_id' => $this->enrollment->id,
            'status' => 'confirmed',
            'icon' => 'check-circle',
        ];
    }
}
