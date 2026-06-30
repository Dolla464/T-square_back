<?php

namespace App\Notifications;

use App\Models\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SessionRescheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AttendanceSession $session,
        public array $data = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $group  = $this->session->learningGroup;
        $course = $group?->course;

        $oldDate  = $this->data['old_date']  ?? null;
        $oldStart = $this->data['old_start'] ?? null;
        $oldEnd   = $this->data['old_end']   ?? null;
        $newDate  = $this->data['new_date']  ?? null;
        $newStart = $this->data['new_start'] ?? null;
        $newEnd   = $this->data['new_end']   ?? null;

        $oldDateStr  = $oldDate  ? (is_string($oldDate)  ? $oldDate  : $oldDate->format('Y-m-d'))  : '—';
        $newDateStr  = $newDate  ? (is_string($newDate)  ? $newDate  : $newDate->format('Y-m-d'))  : '—';
        $oldStartStr = $oldStart ? (is_string($oldStart) ? substr($oldStart, 0, 5) : $oldStart->format('H:i')) : '—';
        $newStartStr = $newStart ? (is_string($newStart) ? substr($newStart, 0, 5) : $newStart->format('H:i')) : '—';
        $oldEndStr   = $oldEnd   ? (is_string($oldEnd)   ? substr($oldEnd, 0, 5)   : $oldEnd->format('H:i'))   : '—';
        $newEndStr   = $newEnd   ? (is_string($newEnd)   ? substr($newEnd, 0, 5)   : $newEnd->format('H:i'))   : '—';

        return [
            'type'         => 'session_rescheduled',
            'title'        => 'Session Rescheduled',
            'message'      => "The session for group '{$group?->group_name}' ({$course?->title}) has been rescheduled. New time: {$newDateStr} {$newStartStr}–{$newEndStr}.",
            'session_id'   => $this->session->id,
            'group_name'   => $group?->group_name,
            'course_title' => $course?->title,
            'old_date'     => $oldDateStr,
            'old_time'     => "{$oldStartStr}–{$oldEndStr}",
            'new_date'     => $newDateStr,
            'new_time'     => "{$newStartStr}–{$newEndStr}",
            'action_url'   => '/notifications',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
