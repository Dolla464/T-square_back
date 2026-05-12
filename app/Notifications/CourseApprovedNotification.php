<?php

namespace App\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // 1. دي أهم واجهة للأداء العالي
use Illuminate\Notifications\Notification;

class CourseApprovedNotification extends Notification implements ShouldQueue
{
    // 2. استخدام الـ Queueable بيخلي لارافيل يقدر يرمي الإشعار ده في الطابور
    use Queueable;

    public $course;

    /**
     * Create a new notification instance.
     */
    public function __construct(Course $course)
    {
        $this->course = $course;
    }

    /**
     * تحديد قنوات الإرسال.
     * بما إنك عايزه في الموقع بس، هنختار 'database'.
     * (مستقبلاً لو حبيت تضيف إيميل، هتزود 'mail' هنا بس!)
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * شكل البيانات اللي هتتخزن في جدول الإشعارات في الداتابيز.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Your course has been approved!🎉',
            'message' => 'You can now start learning from this course: '.$this->course->title,
            'course_id' => $this->course->id,
            'url' => '/courses/'.$this->course->id,
            'icon' => 'check-circle', // لو بتستخدم أيقونات في الفرونت إند
        ];
    }
}
