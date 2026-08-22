<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    public const VIDEO_SOURCE_NONE = 'none';

    public const VIDEO_SOURCE_GOOGLE_DRIVE = 'google_drive';

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'sort_order',
        'is_active',
        'video_source_type',
        'google_drive_file_id',
        'duration_seconds',
        'drive_validation_status',
        'drive_validation_message',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function hasGoogleDriveVideo(): bool
    {
        return $this->video_source_type === self::VIDEO_SOURCE_GOOGLE_DRIVE
            && ! empty($this->google_drive_file_id);
    }
}
