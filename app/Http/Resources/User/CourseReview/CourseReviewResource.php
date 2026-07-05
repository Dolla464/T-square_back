<?php

namespace App\Http\Resources\User\CourseReview;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'overall_comment' => $this->overall_comment,
            'review_status' => $this->review_status,

            'student' => [
                'full_name' => $this->student?->full_name,
                'avatar' => $this->student?->avatar,
            ],

            'course' => [
                'title' => $this->course?->title,
            ],

            'instructor' => [
                'full_name' => $this->instructor?->full_name,
            ],
        ];
    }
}
