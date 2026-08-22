<?php

namespace App\Http\Resources\User\Courses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentLessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'video_source_type' => $this->video_source_type,
            'duration_seconds' => $this->duration_seconds,
            'has_video' => $this->hasGoogleDriveVideo(),
        ];
    }
}
