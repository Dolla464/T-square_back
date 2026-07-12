<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Notifications\Notification;

class InstructorNewEnrollmentNotification extends Notification
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
            'type' => 'instructor_new_enrollment',
            'title' => 'New Student Enrollment',
            'message' => ($this->student->full_name ?? 'A student').' enrolled in "'.$this->course->title.'".',
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'icon' => 'person-plus',
        ];
    }
}
