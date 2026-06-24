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
            'students' => $this->when($this->relationLoaded('assigned_students'), function () {
                return $this->assigned_students->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'full_name' => $student->full_name,
                        'email' => $student->email,
                        'phone' => $student->phone,
                        'is_completed' => (bool) $student->is_completed,
                        'completed_at' => $student->completed_at,
                        'assigned_at' => $student->assigned_at ? date('Y-m-d', strtotime($student->assigned_at)) : null,
                    ];
                });
            }),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d') : null,
        ];
    }
}
