<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    protected $fillable = ['attempt_id', 'question_id', 'choice_id'];

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
}
