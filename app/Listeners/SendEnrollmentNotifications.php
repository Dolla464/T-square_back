<?php

namespace App\Listeners;

use App\Events\CoursePurchased;
use App\Models\User;
use App\Notifications\CourseApprovedNotification;
use App\Notifications\NewEnrollmentAdminNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendEnrollmentNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(CoursePurchased $event): void
    {
        // 1. هنجيب الطالب من الاشتراك
        $student = $event->enrollment->student;
        $course = $event->enrollment->course;

        // 2. إرسال الإشعار للطالب
        if ($student->user) {
            $student->user->notify(new CourseApprovedNotification($course));
        }

        // وتقدر هنا كمان تبعت إشعار للأدمن لو حابب
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new NewEnrollmentAdminNotification($course, $student));
    }
}
