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
        'field',
        'bio',
        'gender',
        'insta_url',
        'linkedin_url',
        'facebook_url',
        'status',
    ];

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/'.$value) : asset('assets/default-instructor.png'),
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
     * كل الطلاب المسجّلين في أي كورس لهذا المدرب.
     *
     * المسار: Instructor → Course → Enrollment → Student
     * ملاحظة: بعد migration 2026_05_16، تم نقل group_id من جدول students
     * إلى جدول enrollments، لذا لا يمكن استخدام hasManyThrough مباشرةً
     * عبر LearningGroup. نمر الآن عبر Course → Enrollment.
     */
    public function enrollmentsViaCoursesRelation()
    {
        return $this->hasManyThrough(
            \App\Models\Enrollment::class,
            Course::class,
            'instructor_id', // FK على courses يشير إلى instructors.id
            'course_id',     // FK على enrollments يشير إلى courses.id
            'id',            // PK على instructors
            'id'             // PK على courses
        );
    }

    /**
     * جلب الطلاب كـ Builder — استخدم هذه الدالة بدلاً من علاقة students()
     * مثال الاستخدام: $instructor->getStudentsQuery()->get()
     */
    public function getStudentsQuery()
    {
        $courseIds = $this->courses()->pluck('id');

        return Student::whereHas('enrollments', function ($q) use ($courseIds) {
            $q->whereIn('course_id', $courseIds);
        })->distinct();
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
            'reviews_count' => $stats->total ?? 0,
        ]);
    }
}
