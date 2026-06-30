<?php

namespace App\Models;

use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'total_amount',
        'status',
        'status_changed_at',
        'billing_name',
        'billing_email',
        'billing_phone',
        'notes',
    ];

    protected $casts = [
        'status_changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Order $order) {
            // isDirty() here (before save) — not wasChanged(), which only works after save (saved/updated).
            // Sets status_changed_at in the same INSERT/UPDATE as status.
            if ($order->isDirty('status')) {
                $order->status_changed_at = now();
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'order_id');
    }
}
