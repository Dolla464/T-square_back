<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'video_source_type' => $this->video_source_type,
            'google_drive_file_id' => $this->google_drive_file_id,
            'duration_seconds' => $this->duration_seconds,
            'drive_validation_status' => $this->drive_validation_status,
            'drive_validation_message' => $this->drive_validation_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
