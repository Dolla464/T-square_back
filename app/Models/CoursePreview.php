<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoursePreview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'title',
        'video_url',
        'description',
        'video_provider',
        'duration_seconds',
        'sort_order',
    ];

    public function getVideoUrlAttribute($value)
    {
        if (! $value) {
            return null;
        }

        // إذا كان الرابط خارجياً بالفعل (YouTube / Drive)
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // إذا كان المرفوع محلياً، أضف رابط التخزين الكامل
        // تأكد من أن APP_URL في ملف .env مضبوط بشكل صحيح
        return asset('storage/'.$value);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
