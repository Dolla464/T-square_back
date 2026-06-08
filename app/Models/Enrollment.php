<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'order_id',
        'group_id',
        'price_paid',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // العلاقات
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function learningGroup()
    {
        return $this->belongsTo(LearningGroup::class, 'group_id');
    }

    /**
     * شهادة هذا الاشتراك — تُعثر عليها عبر (student_id + course_id)
     * لا يوجد enrollment_id على جدول certificates، لذا نقيّد بـ course_id.
     */
    public function certificate()
    {
        return $this->hasOne(Certificate::class, 'student_id', 'student_id')
            ->where('course_id', $this->course_id);
    }

    /**
     * Helper Function: تحديد الكورس كمكتمل
     */
    public function markAsCompleted()
    {
        return $this->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}
