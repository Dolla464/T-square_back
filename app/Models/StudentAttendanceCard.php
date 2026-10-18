<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendanceCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'token_hash',
        'token_suffix',
        'issued_at',
        'revoked_at',
        'issued_by_user_id',
        'group_attendance_card_bulk_operation_id',
        'revoked_by_user_id',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
}
