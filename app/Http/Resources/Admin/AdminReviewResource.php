<?php

namespace App\Http\Resources\Admin;

use App\Models\CourseReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof CourseReview) {
            return [
                'id'                => $this->id,
                'course_id'         => $this->course_id,
                'student_id'        => $this->student_id,
                'review_status'     => $this->review_status,
                'instructor_id'     => $this->instructor_id,
                'content_rating'    => $this->content_rating,
                'instructor_rating' => $this->instructor_rating,
                'center_rating'     => $this->center_rating,
                'rating'            => $this->rating,
                'overall_comment'   => $this->overall_comment,
                'course_title'      => $this->course?->title,
                'student_name'      => $this->student?->full_name,
                'instructor_name'   => $this->instructor?->full_name,
                'created_at'        => $this->created_at?->format('Y-m-d H:i:s'),
                'updated_at'        => $this->updated_at?->format('Y-m-d H:i:s'),
                'has_review'        => true,
            ];
        }

        return is_array($this->resource)
            ? $this->resource
            : (array) $this->resource;
    }
}
