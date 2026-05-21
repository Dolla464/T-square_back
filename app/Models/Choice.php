<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Choice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['question_id', 'choice_text', 'is_correct'];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    // The choice belongs to one question
    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    // The students who chose this choice specifically
    public function chosenBy()
    {
        return $this->hasMany(Answer::class, 'choice_id');
    }
}
