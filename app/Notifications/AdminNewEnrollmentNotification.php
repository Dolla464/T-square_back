<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminNewEnrollmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Course $course,
        public readonly Student $student,
        public readonly Enrollment $enrollment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Student Enrollment')
            ->line('A new student has enrolled in a course.')
            ->line('Student: ' . $this->student->full_name)
            ->line('Course: ' . $this->course->title)
            ->action('View Enrollments', url('/admin/courses/' . $this->course->id . '/enrollments'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New Enrollment',
            'message' => $this->student->full_name . ' enrolled in "' . $this->course->title . '".',
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'enrollment_id' => $this->enrollment->id,
            'url' => '/admin/courses/' . $this->course->id . '/enrollments',
            'icon' => 'users',
        ];
    }
}
