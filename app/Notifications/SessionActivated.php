<?php

namespace App\Notifications;

use App\Models\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SessionActivated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AttendanceSession $session
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        $group = $this->session->learningGroup;
        $course = $group?->course;

        return [
            'type' => 'session_activated',
            'title' => 'Session Activated',
            'message' => "Your session '{$course?->title}' ({$group?->group_name}) is now active.",
            'session_id' => $this->session->id,
            'course_id' => $course?->id,
            'group_name' => $group?->group_name,
            'course_title' => $course?->title,
            'qr_code' => $this->session->qr_code,
            'start_time' => $this->session->schedule?->start_time?->format('H:i'),
            'room' => $this->session->schedule?->room,
            'icon' => 'calendar-check',
            'action_url' => '/instructor/attendance?session='.$this->session->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}