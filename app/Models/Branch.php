<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function learningGroups(): HasMany
    {
        return $this->hasMany(LearningGroup::class);
    }

    public function attendanceDevices(): HasMany
    {
        return $this->hasMany(AttendanceDevice::class);
    }
}
