<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'certificate_url',
        'certificate_num',
        'issued_at',
    ];

    // الشهادة تخص طالب واحد
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // الشهادة تخص كورس واحد
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // الشهادة تخص تسجيلات الطالب في الكورس
    public function enrollments()
    {
        // NOTE: This relation can't reliably scope by both student_id & course_id
        // during eager-loading in SQLite without invalid whereColumn references.
        // Admin listing hydrates the exact enrollments set in the service layer
        // to avoid N+1 while keeping queries valid.
        return $this->hasMany(Enrollment::class, 'student_id', 'student_id');
    }
}
