<?php

namespace App\Notifications;

use App\Models\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SessionActivated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AttendanceSession $session
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $group  = $this->session->learningGroup;
        $course = $group?->course;

        $isStudent = $notifiable->hasRole('student');

        $message = $isStudent
            ? "Your session for '{$course?->title}' ({$group?->group_name}) is now active. You can register your attendance."
            : "Your session '{$course?->title}' ({$group?->group_name}) is now active.";

        $payload = [
            'type'         => 'session_activated',
            'title'        => 'Session Activated',
            'message'      => $message,
            'session_id'   => $this->session->id,
            'course_id'    => $course?->id,
            'group_name'   => $group?->group_name,
            'course_title' => $course?->title,
            'start_time'   => $this->session->schedule?->start_time?->format('H:i'),
            'room'         => $this->session->schedule?->room,
            'icon'         => 'calendar-check',
        ];

        if (! $isStudent) {
            $payload['qr_code'] = $this->session->qr_code;
        }

        return $payload;
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
