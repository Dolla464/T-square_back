<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Choice extends Model
{
    use HasFactory;

    protected $fillable = ['question_id', 'choice_text', 'is_correct'];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    // الاختيار ينتمي لسؤال واحد
    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    // ٢. الطلاب الذين اختاروا هذا الاختيار بالتحديد
    public function chosenBy()
    {
        return $this->hasMany(Answer::class, 'choice_id');
    }
}
