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
     * Calculate the time spent on the exam
     */
    public function getDurationAttribute()
    {
        if ($this->started_at && $this->finished_at) {
            return $this->started_at->diffInMinutes($this->finished_at) . ' Minutes';
        }

        return 'Not finished';
    }

    public function calculateScore()
    {
        // With one query, we collect all the scores from the answers table
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

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'attempt_questions')
            ->withTimestamps();
    }

    public function answers()
    {
        return $this->hasMany(Answer::class, 'attempt_id');
    }
}
