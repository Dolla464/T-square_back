<?php

namespace App\Http\Resources\Admin;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ActivityLog */
class AdminActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'role' => $this->role,
            'http_method' => $this->http_method,
            'route_name' => $this->route_name,
            'path' => $this->path,
            'description' => $this->description,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'request_data' => $this->request_data,
            'response_status' => $this->response_status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
