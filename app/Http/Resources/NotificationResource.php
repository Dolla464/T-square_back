<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'icon' => $data['icon'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'enrollment_id' => $data['enrollment_id'] ?? null,
            'exam_id' => $data['exam_id'] ?? null,
            'attempt_id' => $data['attempt_id'] ?? null,
            'student_id' => $data['student_id'] ?? null,
            'status' => $data['status'] ?? null,
            'score' => $data['score'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'group_name' => $data['group_name'] ?? null,
            'course_title' => $data['course_title'] ?? null,
            'qr_code' => $data['qr_code'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'room' => $data['room'] ?? null,
            'session_date' => $data['session_date'] ?? null,
            'reason' => $data['reason'] ?? null,
            'old_date' => $data['old_date'] ?? null,
            'old_time' => $data['old_time'] ?? null,
            'new_date' => $data['new_date'] ?? null,
            'new_time' => $data['new_time'] ?? null,
            'action_url' => $data['action_url'] ?? $data['url'] ?? $data['action_link'] ?? null,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}
