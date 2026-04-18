<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'short_description',
        'description',
        'thumbnail',
        'cover_image',
        'preview_video',
        'google_drive_link',
        'attendance_type',
        'price_before',
        'discount_price',
        'price',
        'level',
        'language',
        'duration_weeks',
        'duration_hours',
        'status',
        'is_featured',
        'is_free',
        'category_id',
        'instructor_id',
        'published_at'
    ];

    protected static function booted()
    {
        static::saving(function ($course) {
            // 1. إنشاء الـ Slug أوتوماتيك
            if (empty($course->slug)) {
                $course->slug = Str::slug($course->title);
            }

            // 2. حساب السعر النهائي أوتوماتيك
            // لو الكورس مجاني السعر 0، لو فيه خصم نطرحه
            if ($course->is_free) {
                $course->price = 0;
            } else {
                $course->price = $course->price_before - $course->discount_price;
            }
        });
    }

    /**
     * تحديث إحصائيات التقييم للكورس
     * بيتم استدعاؤها من موديل CourseReview عند الحفظ أو المسح
     */
    public function updateRatingStats()
    {
        $stats = $this->reviews()
            ->selectRaw('AVG(rating) as average, COUNT(*) as total')
            ->first();

        $this->update([
            'avg_rating' => round($stats->average ?? 0, 2),
            'reviews_count' => $stats->total ?? 0
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? asset('storage/' . $value) : asset('assets/default-course.png'),
        );
    }

    // العلاقات
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }

    public function learningGroups()
    {
        return $this->hasMany(LearningGroup::class);
    }

    public function learnings()
    {
        return $this->hasMany(CourseLearning::class);
    }

    public function previews()
    {
        return $this->hasMany(CoursePreview::class)->orderBy('sort_order');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    // هات الطلاب المشتركين في الكورس
    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrollments');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'course_tag');
    }

    // جلب كل المراجعات الخاصة بالكورس
    public function reviews()
    {
        return $this->hasMany(CourseReview::class);
    }

    // علاقة لحساب متوسط التقييم بسرعة
    public function averageRating()
    {
        return $this->reviews()->avg('rating');
    }
}
