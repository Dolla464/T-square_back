<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Student extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'enrollment_number',
        'group_id',
        'avatar',
        'gender',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function learningGroup()
    {
        return $this->belongsTo(LearningGroup::class, 'group_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    // هات الكورسات اللي الطالب مشترك فيها مباشرة
    public function courses()
    {
        return $this->belongsToMany(Course::class, 'enrollments');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    // بجيب كل الامتحانات المتاحة للطالب بناءً على الكورسات اللي عمل لها Enrollment
    public function availableExams()
    {
        return $this->hasManyThrough(
            Exam::class,
            Enrollment::class,
            'student_id', // FK في جدول enrollments
            'course_id',  // FK في جدول exams
            'id',         // PK في جدول students
            'course_id'   // PK في جدول enrollments
        );
    }

    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    // مراجعات الطالب
    public function reviews()
    {
        return $this->hasMany(CourseReview::class);
    }

    // الكورسات اللي الطالب قيمها (علاقة Many-to-Many عبر جدول المراجعات)
    public function reviewedCourses()
    {
        return $this->belongsToMany(Course::class, 'course_reviews');
    }
}
