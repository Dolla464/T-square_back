<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CourseInstructor extends Pivot
{
    protected $table = 'course_instructor';

    public $incrementing = true;

    protected $fillable = [
        'course_id',
        'instructor_id',
        'sort_order',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function learningGroups(): HasMany
    {
        return $this->hasMany(LearningGroup::class);
    }

    public function reviewRatings(): HasMany
    {
        return $this->hasMany(CourseReviewInstructorRating::class);
    }
}
