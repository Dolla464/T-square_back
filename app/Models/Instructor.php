<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'avatar',
        'bio',
        'gender',
        'insta_url',
        'linkedin_url',
        'facebook_url',
        'status'
    ];

    protected function avatar(): Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn($value) => $value ? asset('storage/' . $value) : asset('assets/default-instructor.png'),
        );
    }

    // علاقة المدرب بحسابه في جدول المستخدمين
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function learningGroups()
    {
        return $this->hasMany(LearningGroup::class, 'instructor_id');
    }

    /**
     * هات لي كل الطلاب اللي عند المدرب ده في كل مجموعاته
     */
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            LearningGroup::class,
            'instructor_id', // المفتاح الأجنبي في جدول المجموعات
            'group_id',      // المفتاح الأجنبي في جدول الطلاب
            'id',            // المفتاح الأساسي في جدول المدربين
            'id'             // المفتاح الأساسي في جدول المجموعات
        );
    }

    // جلب كل التقييمات اللي استلمها المدرب عبر كل كورساته
    public function reviews()
    {
        return $this->hasMany(CourseReview::class);
    }

    /**
     * تحديث إحصائيات تقييم المحاضر بناءً على كل تقييمات كورساته
     */
    public function updateRatingStats()
    {
        // بنحسب المتوسط والعدد من جدول المراجعات اللي مرتبطة بالـ instructor_id ده
        $stats = CourseReview::where('instructor_id', $this->id)
            ->selectRaw('AVG(instructor_rating) as average, COUNT(*) as total')
            ->first();

        $this->update([
            'avg_rating' => round($stats->average ?? 0, 2),
            'reviews_count' => $stats->total ?? 0
        ]);
    }
}
