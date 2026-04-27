<?php

namespace App\Http\Resources\User\Courses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // الـ enrollment الخاص بالطالب للكورس
        $enrollment = $this->enrollments->first();

        return [
            // ── بيانات الكورس الأساسية ─────────────────────────────────────
            'id'                => $this->id,
            'title'             => $this->title,
            'thumbnail'         => $this->thumbnail,
            'google_drive_link' => $this->google_drive_link,

            // ── بيانات المحاضر ─────────────────────────────────────────────
            'instructor' => $this->whenLoaded('instructor', fn() => [
                'full_name' => $this->instructor->full_name,
                'field'     => $this->instructor->field,
            ]),

            // ── حالة الـ Enrollment ─────────────────────────────────────────
            'enrollment' => $enrollment ? [
                'status'       => $enrollment->is_completed ? 'completed' : 'in_progress',
                'completed_at' => $enrollment->completed_at?->toDateTimeString(),
            ] : null,
        ];
    }
}
