<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'exam_id', 'started_at', 'finished_at', 'score'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * حساب الوقت المستغرق في الامتحان
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
        $totalMarks = 0;
        // بنلف على كل إجابة ونشوف لو الـ choice المختار هو الـ correct
        foreach ($this->answers as $answer) {
            if ($answer->choice->is_correct) {
                $totalMarks += $answer->question->marks;
            }
        }
        $this->update(['score' => $totalMarks]);
        return $totalMarks;
    }

    public function answers()
    {
        return $this->hasMany(Answer::class, 'attempt_id');
    }
}
