<?php

namespace App\Notifications;

use App\Models\LearningGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InstructorGroupAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly LearningGroup $group,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $course    = $this->group->course;
        $startDate = $this->group->start_date
            ? (is_string($this->group->start_date) ? $this->group->start_date : $this->group->start_date->format('Y-m-d'))
            : null;

        return [
            'type'         => 'group_assigned',
            'title'        => 'New Group Assignment',
            'message'      => 'You have been assigned to group "'.$this->group->group_name.'" for "'.$course?->title.'".'
                . ($startDate ? " Starting: {$startDate}." : ''),
            'group_id'     => $this->group->id,
            'group_name'   => $this->group->group_name,
            'course_id'    => $course?->id,
            'course_title' => $course?->title,
            'start_date'   => $startDate,
            'icon'         => 'people',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
