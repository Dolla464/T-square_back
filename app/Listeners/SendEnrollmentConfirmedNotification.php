<?php

namespace App\Listeners;

use App\Events\CoursePurchased;
use App\Notifications\StudentEnrollmentConfirmedNotification;

class SendEnrollmentConfirmedNotification
{
    public function handle(CoursePurchased $event): void
    {
        $enrollment = $event->enrollment->loadMissing(['course', 'student.user', 'order']);

        if ($enrollment->order?->status !== 'completed') {
            return;
        }

        $student = $enrollment->student;
        $course = $enrollment->course;

        if (! $student?->user || ! $course) {
            return;
        }

        $student->user->notify(new StudentEnrollmentConfirmedNotification($course, $enrollment));
    }
}
