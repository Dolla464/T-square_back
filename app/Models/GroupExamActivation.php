<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupExamActivation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'exam_id',
        'learning_group_id',
        'activated_by',
        'activated_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function learningGroup(): BelongsTo
    {
        return $this->belongsTo(LearningGroup::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'activated_by');
    }
}
