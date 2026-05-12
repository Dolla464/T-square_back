<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'exam_id', 'status', 'started_at', 'finished_at', 'score'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * حساب الوقت المستغرق في الامتحان
     */
    public function getDurationAttribute()
    {
        if ($this->started_at && $this->finished_at) {
            return $this->started_at->diffInMinutes($this->finished_at).' Minutes';
        }

        return 'Not finished';
    }

    public function calculateScore()
    {
        // بطلقة واحدة للداتابيز، هنجمع كل الدرجات المستحقة من جدول الإجابات
        $totalScore = $this->answers()->where('is_correct', true)->sum('marks_earned');

        $this->fill([
            'score' => $totalScore,
            'status' => 'completed',
            'finished_at' => now(),
        ])->save();

        return $totalScore;
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class, 'attempt_id');
    }
}
