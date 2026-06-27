<?php

namespace App\Notifications;

use App\Models\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
        return ['database', 'broadcast']; // أو ['mail', 'database', 'broadcast']
    }

    public function toDatabase(object $notifiable): array
    {
        $group = $this->session->learningGroup;
        $course = $group?->course;

        return [
            'title' => 'Session Activated',
            'message' => "Your session '{$course?->title}' ({$group?->group_name}) is now active.",
            'session_id' => $this->session->id,
            'group_name' => $group?->group_name,
            'course_title' => $course?->title,
            'qr_code' => $this->session->qr_code,
            'start_time' => $this->session->schedule?->start_time?->format('H:i'),
            'room' => $this->session->schedule?->room,
            'type' => 'session_activated',
            'action_url' => "/instructor/attendance/sessions/{$this->session->id}",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $group = $this->session->learningGroup;
        $course = $group?->course;

        return (new MailMessage)
            ->subject('Session Activated - ' . $course?->title)
            ->greeting('Hello ' . $notifiable->name)
            ->line("Your session '{$course?->title}' ({$group?->group_name}) is now active.")
            ->line("Room: {$this->session->schedule?->room}")
            ->line("QR Code: {$this->session->qr_code}")
            ->action('View Session', url("/instructor/attendance/sessions/{$this->session->id}"))
            ->line('Students can now scan the QR code to mark attendance.');
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}