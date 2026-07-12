<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseReviewInstructorRating extends Model
{
    protected $fillable = [
        'course_review_id',
        'course_instructor_id',
        'instructor_rating',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(CourseReview::class, 'course_review_id');
    }

    public function courseInstructor(): BelongsTo
    {
        return $this->belongsTo(CourseInstructor::class);
    }
}
