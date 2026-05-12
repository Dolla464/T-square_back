<?php

namespace App\Notifications;

use App\Models\Course;
// أو User حسب ما أنت مسميه
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewEnrollmentAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $course;

    public $student;

    public function __construct(Course $course, $student)
    {
        $this->course = $course;
        $this->student = $student;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New Enrollment confirmed! 💰',
            'message' => "Student {$this->student->full_name} enrolled in course: {$this->course->title}",
            'course_id' => $this->course->id,
            'url' => '/admin/courses/'.$this->course->id.'/enrollments',
            'icon' => 'shopping-cart',
        ];
    }
}
