<?php

namespace App\Models;

use App\Observers\CourseReviewObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([CourseReviewObserver::class])]
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
    ];

    public const REVIEW_STATUS_ACCEPTED = 'accepted';
    public const REVIEW_STATUS_PENDING = 'pending';
    public const REVIEW_STATUS_REJECTED = 'rejected';

    /**
     * الـ Boot Method: هو المحرك اللي بيراقب الأحداث في الموديل
     */
    protected static function boot()
    {
        parent::boot();

        // ١. قبل إنشاء التقييم أو تحديثه: احسب المتوسط الكلي
        static::saving(function ($review) {
            $review->rating = (
                $review->content_rating +
                $review->instructor_rating +
                $review->center_rating
            ) / 3;

            // لو مفيش status حطه active تلقائي
            $review->review_status ??= self::REVIEW_STATUS_PENDING;
        });

        // ٢. بعد الحفظ (إنشاء أو تعديل): حدث إحصائيات الكورس والمحاضر
        static::saved(function ($review) {
            $review->updateAggregatedStats();
        });

        // ٣. بعد المسح: حدث الإحصائيات أيضاً
        static::deleted(function ($review) {
            $review->updateAggregatedStats();
        });
    }

    /**
     * دالة مركزية لتحديث إحصائيات الكورس والمحاضر
     */
    public function updateAggregatedStats()
    {
        // تحديث إحصائيات الكورس المرتبط
        if ($this->course) {
            $this->course->updateRatingStats();
        }

        // تحديث إحصائيات المحاضر المرتبط
        if ($this->instructor) {
            $this->instructor->updateRatingStats();
        }
    }

    /**
     * Scope للتقييمات النشطة فقط
     */
    public function scopeActive($query)
    {
        return $query->where('review_status', self::REVIEW_STATUS_ACCEPTED);
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
}