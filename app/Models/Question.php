<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['exam_id', 'question_text', 'marks'];

    // السؤال ينتمي لامتحان واحد
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    // جلب الإجابة الصحيحة فقط (مفيد جداً في التصحيح)
    public function correctChoice()
    {
        return $this->hasOne(Choice::class)->where('is_correct', true);
    }

    // جلب كل الاختيارات التابعة للسؤال
    public function choices()
    {
        return $this->hasMany(Choice::class, 'question_id');
    }

    // ٢. جلب كل إجابات الطلاب على هذا السؤال (عبر كل المحاولات)
    public function studentAnswers()
    {
        return $this->hasMany(Answer::class);
    }
}
