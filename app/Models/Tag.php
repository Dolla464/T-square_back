<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Solution;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    // لو عايز الـ slug يتكريت لوحده بمجرد ما تبعت الاسم
    protected static function booted()
    {
        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_tag');
    }

    public function solutions()
    {
        return $this->belongsToMany(Solution::class, 'solution_tag');
    }
}
