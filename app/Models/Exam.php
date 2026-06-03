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
        'passing_mark',
        'is_final',
        'max_attempts',
        'questions_per_attempt',
        'shuffle_questions',
    ];

    // Convert data types when dealing with them in the code
    protected $casts = [
        'is_active' => 'boolean',
        'is_final' => 'boolean',
        'shuffle_questions' => 'boolean',
        'max_attempts' => 'integer',
        'questions_per_attempt' => 'integer',
        'duration' => 'integer',
        'total_marks' => 'float',
        'passing_mark' => 'float',
    ];

    // The exam belongs to one course
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // The exam belongs to many questions
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    // The exam belongs to many attempts
    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }
}