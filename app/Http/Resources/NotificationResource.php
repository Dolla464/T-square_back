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
            'id'           => $this->id,
            'type'         => $data['type'] ?? null,
            'title'        => $data['title'] ?? '',
            'message'      => $data['message'] ?? '',
            'session_id'   => $data['session_id'] ?? null,
            'group_name'   => $data['group_name'] ?? null,
            'course_title' => $data['course_title'] ?? null,
            'qr_code'      => $data['qr_code'] ?? null,
            'start_time'   => $data['start_time'] ?? null,
            'room'         => $data['room'] ?? null,
            'action_url'   => $data['action_url'] ?? null,
            'is_read'      => $this->read_at !== null,
            'read_at'      => $this->read_at?->toISOString(),
            'created_at'   => $this->created_at->toISOString(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}
