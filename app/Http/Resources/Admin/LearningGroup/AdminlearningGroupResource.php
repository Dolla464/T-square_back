<?php

namespace App\Http\Resources\Admin\LearningGroup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLearningGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_name' => $this->group_name,
            'course_id' => $this->course_id,
            'instructor_id' => $this->instructor_id,
            
            // data of the relationships (only show if loaded)
            'course_title' => $this->whenLoaded('course', fn() => $this->course->title),
            'instructor_name' => $this->whenLoaded('instructor', fn() => $this->instructor->full_name),
            
            'students_count' => $this->whenCounted('students'),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d') : null,
        ];
    }
}
