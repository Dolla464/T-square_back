<?php

namespace App\Listeners;

use App\Events\StudentEnrolled;
use App\Models\User;
use App\Notifications\AdminNewEnrollmentNotification;
use App\Notifications\InstructorNewEnrollmentNotification;
use App\Notifications\StudentEnrollmentPendingNotification;
use App\Support\CourseInstructorNotifier;
use Illuminate\Support\Facades\Notification;

class SendEnrollmentNotifications
{
    public function handle(StudentEnrolled $event): void
    {
        $student = $event->student;
        $course = $event->course;
        $enrollment = $event->enrollment;

        $enrollment->loadMissing('order');

        if ($student->user && $enrollment->order?->status === 'pending') {
            $student->user->notify(new StudentEnrollmentPendingNotification($course, $enrollment));
        }

        $admins = User::role('admin')->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new AdminNewEnrollmentNotification($course, $student, $enrollment));
        }

        CourseInstructorNotifier::notifyAll(
            $course,
            new InstructorNewEnrollmentNotification($course, $student, $enrollment)
        );
    }
}
