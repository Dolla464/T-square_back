<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'exam_id',
        'question_text',
        'question_image',
        'question_code',
        'question_code_language',
        'marks',
    ];

    /**
     * Monitor model operations and apply automatic deletion and restoration of choices
     */
    protected static function booted()
    {
        // 1. When the question is soft deleted
        static::deleted(function ($question) {
            // If the admin performs forceDelete, the database will behave (if Cascade is used)
            // If soft delete is performed normally, we will perform soft delete for the choices
            if (! $question->isForceDeleting()) {
                $question->choices()->delete();
            }
        });

        // 2. When the question is restored
        static::restored(function ($question) {
            // We will restore all the choices that were deleted with the question
            $question->choices()->restore();
        });
    }

    protected $casts = [
        'marks' => 'float',
    ];

    protected function questionImageUrl(): Attribute
    {
        return Attribute::get(function ($value, array $attributes) {
            $path = $attributes['question_image'] ?? null;

            if (! $path) {
                return null;
            }

            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $path;
            }

            return asset('storage/'.$path);
        });
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    // The most efficient way to get the correct answer only
    public function correctChoice()
    {
        return $this->hasOne(Choice::class)->ofMany([
            'id' => 'max'
        ], function ($query) {
            $query->where('is_correct', true);
        });
    }

    public function choices()
    {
        return $this->hasMany(Choice::class, 'question_id')->orderBy('id');
    }

    public function studentAnswers()
    {
        return $this->hasMany(Answer::class);
    }
}
