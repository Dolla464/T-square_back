<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupAttendanceCardBulkOperation extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABANDONED = 'abandoned';

    public const MODE_ISSUE_NEW_ONLY = 'issue_new_only';

    public const MODE_REISSUE_ACTIVE_ONLY = 'reissue_active_only';

    public const MODE_ISSUE_ALL = 'issue_all';

    protected $fillable = [
        'client_operation_id',
        'learning_group_id',
        'initiated_by_user_id',
        'mode',
        'confirm_reissue',
        'revocation_reason',
        'request_fingerprint',
        'snapshot_count',
        'status',
        'next_batch_index',
        'completed_at',
        'abandoned_at',
    ];

    protected function casts(): array
    {
        return [
            'confirm_reissue' => 'boolean',
            'completed_at' => 'datetime',
            'abandoned_at' => 'datetime',
        ];
    }

    public function learningGroup(): BelongsTo
    {
        return $this->belongsTo(LearningGroup::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(GroupAttendanceCardBulkOperationStudent::class);
    }
}
