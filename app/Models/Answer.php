<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $fillable = [
        'attempt_id',
        'question_id',
        'choice_id',
        'answer_text',
        'graded_at',
        'graded_by',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'marks_earned' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    // الإجابة تتبع محاولة معينة
    public function attempt()
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    // الإجابة مرتبطة بسؤال
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    // الاختيار اللي الطالب اختاره
    public function choice()
    {
        return $this->belongsTo(Choice::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'graded_by');
    }
}
