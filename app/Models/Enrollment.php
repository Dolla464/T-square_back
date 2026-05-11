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
        'price_paid',
        'is_completed',
        'completed_at'
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

    /**
     * Helper Function: الحصول على الشهادة الخاصة بالتسجيل
     */
    public function certificate()
    {
        return $this->hasOne(Certificate::class, 'enrollment_id');
    }

    /**
     * Helper Function: تحديد الكورس كمكتمل
     */
    public function markAsCompleted()
    {
        return $this->update([
            'is_completed' => true,
            'completed_at' => now()
        ]);
    }
}
