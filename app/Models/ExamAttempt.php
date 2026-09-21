<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    use HasFactory;

    /** Statuses allowed for post-submit answer review */
    public const REVIEWABLE_STATUSES = ['passed', 'failed', 'completed', 'timed_out', 'awaiting_grading'];

    public const STATUS_ONGOING = 'ongoing';

    public const STATUS_AWAITING_GRADING = 'awaiting_grading';

    protected $fillable = [
        'student_id',
        'exam_id',
        'duration_minutes',
        'started_at',
        'finished_at',
        'graded_at',
        'graded_by',
        'status',
        'score',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'graded_at' => 'datetime',
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

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class)->withTrashed();
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'attempt_questions')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('attempt_questions.sort_order')
            ->orderBy('questions.id');
    }

    public function questionsWithTrashed()
    {
        return $this->belongsToMany(Question::class, 'attempt_questions')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->withTrashed()
            ->orderBy('attempt_questions.sort_order')
            ->orderBy('questions.id');
    }

    public function answers()
    {
        return $this->hasMany(Answer::class, 'attempt_id');
    }

    public function integrityEvents()
    {
        return $this->hasMany(ExamAttemptIntegrityEvent::class, 'exam_attempt_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function scopeReviewable($query)
    {
        return $query->whereIn('status', self::REVIEWABLE_STATUSES);
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, self::REVIEWABLE_STATUSES, true);
    }

    public function gradedBy()
    {
        return $this->belongsTo(Instructor::class, 'graded_by');
    }

    public function resolveIsPassed(): ?bool
    {
        return match ($this->status) {
            self::STATUS_AWAITING_GRADING => null,
            'passed' => true,
            'failed', 'timed_out' => false,
            default => null,
        };
    }
}
