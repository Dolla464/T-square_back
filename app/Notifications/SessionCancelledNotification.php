<?php

namespace App\Notifications;

use App\Models\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SessionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AttendanceSession $session,
        public ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $group  = $this->session->learningGroup;
        $course = $group?->course;

        $effectiveDate = $this->session->override_date ?? $this->session->session_date;
        $dateStr = $effectiveDate
            ? (is_string($effectiveDate) ? $effectiveDate : $effectiveDate->format('Y-m-d'))
            : '—';

        return [
            'type'         => 'session_cancelled',
            'title'        => 'Session Cancelled',
            'message'      => "The session for group '{$group?->group_name}' ({$course?->title}) on {$dateStr} has been cancelled."
                . ($this->reason ? " Reason: {$this->reason}" : ''),
            'session_id'   => $this->session->id,
            'group_name'   => $group?->group_name,
            'course_title' => $course?->title,
            'session_date' => $dateStr,
            'reason'       => $this->reason,
            'action_url'   => '/notifications',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
