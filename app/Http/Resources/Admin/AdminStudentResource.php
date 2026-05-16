<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'user_id'           => $this->user_id,
            'email'             => $this->whenLoaded('user', fn() => $this->user->email),
            'is_verified'       => (bool) optional($this->user)->email_verified_at,
            'full_name'         => $this->full_name,
            'phone'             => $this->phone,
            'enrollment_number' => $this->enrollment_number,
            'avatar'            => $this->avatar
                ? asset('storage/' . $this->avatar)
                : null,
            'gender'            => $this->gender,
            'status'            => $this->status,

            // group_id is now per-enrollment (see enrolled_courses below).
            // Kept as null at the top level to preserve API contract shape.
            'group_id'          => null,
            'learning_group'    => null,

            'enrolled_courses'  => $this->whenLoaded('enrollments', function () {
                return $this->enrollments->map(function ($enrollment) {
                    return [
                        'id'              => $enrollment->course_id,
                        'title'           => $enrollment->course->title,
                        'instructor_name' => $enrollment->course->instructor->full_name,

                        // group_id & group_name now come from the enrollment record
                        'group_id'        => $enrollment->group_id,
                        'group_name'      => optional($enrollment->learningGroup)->group_name,

                        'is_completed'    => (bool) $enrollment->is_completed,
                        'joined_at'       => $enrollment->created_at->format('Y-m-d'),
                        'available_groups' => $enrollment->course->learningGroups->map(fn($group) => [
                            'id'   => $group->id,
                            'name' => $group->group_name,
                        ]),
                    ];
                });
            }),

            'created_at' => $this->created_at?->format('Y-m-d'),
            'updated_at' => $this->updated_at?->format('Y-m-d'),
        ];
    }
}
