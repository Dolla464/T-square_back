<?php

namespace App\Support;

use App\Models\Course;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class CourseInstructorNotifier
{
    public static function notifyAll(Course $course, Notification $notification): void
    {
        $course->loadMissing('instructors.user');

        $users = $course->instructors
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();

        if ($users->isNotEmpty()) {
            NotificationFacade::send($users, $notification);
        }
    }
}
