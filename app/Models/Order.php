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
        'billing_name',
        'billing_email',
        'billing_phone',
        'notes',
    ];

    // العلاقة مع الطالب
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'order_id');
    }
}
