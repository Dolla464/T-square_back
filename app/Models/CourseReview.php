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
        'status',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

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
            $review->status ??= self::STATUS_ACTIVE;
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
        return $query->where('status', self::STATUS_ACTIVE);
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