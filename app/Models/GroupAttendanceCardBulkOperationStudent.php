<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupAttendanceCardBulkOperationStudent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const PLANNED_ISSUE = 'issue';

    public const PLANNED_REISSUE = 'reissue';

    public const PLANNED_SKIP = 'skip';

    protected $fillable = [
        'group_attendance_card_bulk_operation_id',
        'student_id',
        'sequence',
        'planned_action',
        'status',
        'result_code',
        'card_id',
        'token_response_generated_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'token_response_generated_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(GroupAttendanceCardBulkOperation::class, 'group_attendance_card_bulk_operation_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(StudentAttendanceCard::class, 'card_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_SKIPPED,
        ], true);
    }
}
