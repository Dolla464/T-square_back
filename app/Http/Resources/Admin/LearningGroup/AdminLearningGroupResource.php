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
        $dayNames = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        return [
            'id'            => $this->id,
            'group_name'    => $this->group_name,
            'course_id'     => $this->course_id,
            'instructor_id' => $this->instructor_id,
            'start_date'    => $this->start_date?->format('Y-m-d'),
            'end_date'      => $this->end_date?->format('Y-m-d'),
            'status'        => $this->status,

            'course_title'    => $this->whenLoaded('course', fn () => $this->course->title),
            'instructor_name' => $this->whenLoaded('instructor', fn () => $this->instructor->full_name),

            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedules->map(fn ($s) => [
                'id'          => $s->id,
                'day_of_week' => $s->day_of_week,
                'day_name'    => $dayNames[$s->day_of_week] ?? null,
                'start_time'  => $s->start_time?->format('H:i'),
                'end_time'    => $s->end_time?->format('H:i'),
                'room'        => $s->room,
            ])),

            'students_count' => $this->relationLoaded('assigned_students')
                ? $this->assigned_students->count()
                : ($this->students_count ?? 0),
            'students'       => $this->when($this->relationLoaded('assigned_students'), function () {
                return $this->assigned_students->map(function ($student) {
                    return [
                        'id'           => $student->id,
                        'full_name'    => $student->full_name,
                        'email'        => $student->email,
                        'phone'        => $student->phone,
                        'is_completed' => (bool) $student->is_completed,
                        'completed_at' => $student->completed_at,
                        'assigned_at'  => $student->assigned_at
                            ? date('Y-m-d', strtotime($student->assigned_at))
                            : null,
                    ];
                });
            }),

            'created_at' => $this->created_at?->format('Y-m-d'),

            'sync' => $this->when(isset($this->sync_meta), fn () => $this->sync_meta),
        ];
    }
}
