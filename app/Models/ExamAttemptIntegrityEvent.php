<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttemptIntegrityEvent extends Model
{
    public const TYPE_TAB_HIDDEN = 'tab_hidden';

    public const TYPE_TAB_VISIBLE = 'tab_visible';

    public const TYPE_WINDOW_BLUR = 'window_blur';

    public const TYPE_WINDOW_RESIZED = 'window_resized';

    public const TYPES = [
        self::TYPE_TAB_HIDDEN,
        self::TYPE_TAB_VISIBLE,
        self::TYPE_WINDOW_BLUR,
        self::TYPE_WINDOW_RESIZED,
    ];

    protected $fillable = [
        'exam_attempt_id',
        'event_id',
        'event_type',
        'client_at',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'client_at' => 'datetime',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }
}
