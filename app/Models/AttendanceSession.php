<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_group_id',
        'schedule_id',
        'session_date',
        'qr_code',
        'status',
        'override_date',
        'override_start_time',
        'override_end_time',
        'cancellation_reason',
        'release_lesson_on_cancel',
    ];

    protected $casts = [
        'session_date' => 'date',
        'override_date' => 'date',
        'release_lesson_on_cancel' => 'boolean',
    ];

    public function learningGroup()
    {
        return $this->belongsTo(LearningGroup::class);
    }

    public function schedule()
    {
        return $this->belongsTo(LearningGroupSchedule::class, 'schedule_id');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }
}
