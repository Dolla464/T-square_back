<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Notifications\Notification;

class AdminNewEnrollmentNotification extends Notification
{
    public function __construct(
        public readonly Course $course,
        public readonly Student $student,
        public readonly Enrollment $enrollment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'admin_enrollment',
            'title' => 'New Enrollment',
            'message' => $this->student->full_name.' enrolled in "'.$this->course->title.'".',
            'student_id' => $this->student->id,
            'student_name' => $this->student->full_name,
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'enrollment_id' => $this->enrollment->id,
            'icon' => 'people',
        ];
    }
}
