<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoogleStorageAccount extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'name',
        'email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scope',
        'status',
        'last_checked_at',
        'last_error',
        'connected_by',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED
            && ! empty($this->refresh_token);
    }
}
