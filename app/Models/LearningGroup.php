<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_name',
        'course_id',
        'instructor_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    public function schedules()
    {
        return $this->hasMany(LearningGroupSchedule::class);
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class);
    }

    /**
     * Students enrolled in this group, reached through the enrollments pivot.
     */
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            Enrollment::class,
            'group_id',
            'id',
            'id',
            'student_id'
        );
    }
}
