<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Notifications\Notification;

class StudentEnrolledNotification extends Notification
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
            'type' => 'enrollment',
            'title' => 'Enrollment Confirmed',
            'message' => 'You have successfully enrolled in "'.$this->course->title.'".',
            'course_id' => $this->course->id,
            'enrollment_id' => $this->enrollment->id,
            'icon' => 'book',
        ];
    }
}
