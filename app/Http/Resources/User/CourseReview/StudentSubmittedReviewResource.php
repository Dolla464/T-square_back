<?php

namespace App\Http\Resources\User\CourseReview;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentSubmittedReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'content_rating' => $this->content_rating,
            'instructor_rating' => $this->instructor_rating,
            'center_rating' => $this->center_rating,
            'rating' => $this->rating,
            'overall_comment' => $this->overall_comment,
            'review_status' => $this->review_status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'certificate_issued' => (bool) ($this->certificate_issued ?? false),
        ];
    }
}
