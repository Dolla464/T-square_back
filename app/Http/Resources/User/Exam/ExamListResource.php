<?php

namespace App\Http\Resources\User\Exam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'duration'    => $this->duration . ' mins',
            'total_marks' => $this->total_marks,
            'course_name' => $this->course->title,
            // ممكن نضيف حالة المحاولة لو الطالب بدأه قبل كده ومكملش
            'has_attempt' => $this->attempts()->where('student_id', $request->user()->student->id)->exists(),
        ];
    }
}
