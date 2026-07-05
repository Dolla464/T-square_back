<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningGroupSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_group_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'start_time'  => 'datetime:H:i',
        'end_time'    => 'datetime:H:i',
    ];

    public function learningGroup()
    {
        return $this->belongsTo(LearningGroup::class);
    }

    public function attendanceSessions()
    {
        return $this->hasMany(AttendanceSession::class, 'schedule_id');
    }
}
