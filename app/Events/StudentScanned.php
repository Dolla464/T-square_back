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
        $instructorId = $this->record->session->learningGroup->instructor_id;

        return [
            new PrivateChannel("instructor.{$instructorId}"),
        ];
    }

    public function broadcastWith(): array
    {
        $student = $this->record->student;

        return [
            'student_id'   => $this->record->student_id,
            'student_name' => $student?->full_name ?? $student?->user?->name ?? 'Unknown',
            'session_id'   => $this->record->session_id,
            'status'       => $this->record->status,
            'marked_at'    => $this->record->marked_at?->toDateTimeString(),
        ];
    }
}
