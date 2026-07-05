<?php

namespace App\Listeners;

use App\Events\StudentEnrolled;
use App\Models\User;
use App\Notifications\AdminNewEnrollmentNotification;
use App\Notifications\StudentEnrolledNotification;
use Illuminate\Support\Facades\Notification;

class SendEnrollmentNotifications
{
    public function handle(StudentEnrolled $event): void
    {
        $student = $event->student;
        $course = $event->course;
        $enrollment = $event->enrollment;

        if ($student->user) {
            $student->user->notify(new StudentEnrolledNotification($course, $enrollment));
        }

        $admins = User::role('admin')->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new AdminNewEnrollmentNotification($course, $student, $enrollment));
        }
    }
}
