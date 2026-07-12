<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseReview extends Model
{
    protected $fillable = [
        'course_id',
        'student_id',
        'instructor_id',
        'content_rating',
        'instructor_rating',
        'center_rating',
        'rating',
        'overall_comment',
        'review_status',
        'status',
    ];

    public const REVIEW_STATUS_ACCEPTED = 'accepted';
    public const REVIEW_STATUS_PENDING = 'pending';
    public const REVIEW_STATUS_REJECTED = 'rejected';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($review) {
            $review->rating = (
                $review->content_rating +
                $review->instructor_rating +
                $review->center_rating
            ) / 3;

            $review->review_status ??= self::REVIEW_STATUS_PENDING;

            $review->status = match ($review->review_status) {
                self::REVIEW_STATUS_ACCEPTED => self::STATUS_ACTIVE,
                default => self::STATUS_INACTIVE,
            };
        });

        static::saved(fn ($review) => $review->updateAggregatedStats());
        static::deleted(fn ($review) => $review->updateAggregatedStats());
    }

    /**
     * Update cached rating stats on the related course and instructor.
     */
    public function updateAggregatedStats()
    {
        // تحديث إحصائيات الكورس المرتبط
        if ($this->course) {
            $this->course->updateRatingStats();
        }

        // تحديث إحصائيات المحاضرين المقيَّمين
        $this->loadMissing('instructorRatings.courseInstructor.instructor');
        $instructorIds = $this->instructorRatings
            ->map(fn ($rating) => $rating->courseInstructor?->instructor_id)
            ->filter()
            ->unique();

        foreach ($instructorIds as $instructorId) {
            Instructor::find($instructorId)?->updateRatingStats();
        }

        if ($this->instructor) {
            $this->instructor->updateRatingStats();
        }
    }

    /**
     * Scope للتقييمات المعروضة علناً (مقبولة + active)
     */
    public function scopeActive($query)
    {
        return $query
            ->where('review_status', self::REVIEW_STATUS_ACCEPTED)
            ->where('status', self::STATUS_ACTIVE);
    }

    // العلاقات

    // التقييم يخص كورس معين
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // التقييم كتبه طالب معين
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // التقييم موجه لمدرب معين
    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }

    public function instructorRatings()
    {
        return $this->hasMany(CourseReviewInstructorRating::class);
    }
}
