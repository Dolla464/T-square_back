<?php

namespace App\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, Sluggable, SoftDeletes;

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
        'published_at',
        'avg_rating',
        'published_at',
        'status',
        'total_reviews',
        'total_students',
        'total_revenue', // ضفت حقول الإحصائيات هنا لو هتحتاج تحدثها
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_free' => 'boolean',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title', // بنقول للحزمة تاخد الـ slug من حقل الـ title
            ],
        ];
    }

    protected static function booted()
    {
        static::saving(function ($course) {
            //  حساب السعر مع الحماية من القيم الفارغة والسالبة
            if ($course->is_free) {
                $course->price = 0;
            } else {
                $priceBefore = $course->price_before ?? 0;
                $discount = $course->discount_price ?? 0;
                // الدالة max بتضمن إن السعر عمره ما يقل عن صفر لو الخصم أكبر من السعر الأساسي
                $course->price = max(0, $priceBefore - $discount);
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

        // تم التعديل لاستخدام total_reviews بناءً على الميجريشن
        $this->update([
            'avg_rating' => round($stats->average ?? 0, 2),
            'total_reviews' => $stats->total ?? 0,
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

    /**
     * Use the slug column for route model binding instead of id.
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Accessor لضمان عودة رابط صورة الغلاف كاملاً
     */
    protected function coverImage(): Attribute
    {
        return Attribute::get(function ($value) {
            if (! $value) {
                return null; // أو يمكنك وضع رابط صورة افتراضية هنا
            }

            // إذا كان الرابط يبدأ بـ http (مثل صور Faker) يرجعه كما هو
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }

            // يضيف رابط الـ Storage كاملاً للمسار المخزن
            return asset('storage/'.$value);
        });
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::get(function ($value) {
            if (! $value) {
                return asset('assets/default-course.png');
            }

            if (filter_var($value, FILTER_VALIDATE_URL) || Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }

            return asset('storage/'.$value);
        });
    }

    // ================= العلاقات ================= //

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

    public function reviews()
    {
        return $this->hasMany(CourseReview::class);
    }
}
