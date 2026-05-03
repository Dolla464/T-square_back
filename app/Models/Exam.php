<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'duration',
        'total_marks',
        'is_active',
        'passing_mark'
    ];

    // علاقة الامتحان بالكورس
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // علاقة الامتحان بالأسئلة (اللي لسه هنعملها)
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
