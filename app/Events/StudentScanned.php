<?php

namespace App\Events;

use App\Models\AttendanceRecord;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentScanned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly AttendanceRecord $record) {}

    public function broadcastOn(): array
    {
        $group = $this->record->session->learningGroup;
        $group->loadMissing('course.instructors', 'courseInstructor');

        $instructorIds = collect([$group->instructor_id])
            ->merge($group->course?->instructors?->pluck('id') ?? [])
            ->filter()
            ->unique();

        return $instructorIds
            ->map(fn ($id) => new PrivateChannel("instructor.{$id}"))
            ->values()
            ->all();
    }

    public function broadcastWith(): array
    {
        $student = $this->record->student;

        return [
            'student_id' => $this->record->student_id,
            'student_name' => $student?->full_name ?? $student?->user?->name ?? 'Unknown',
            'session_id' => $this->record->session_id,
            'status' => $this->record->status,
            'marked_at' => $this->record->marked_at?->toDateTimeString(),
        ];
    }
}
