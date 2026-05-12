<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'image',
        'parent_id',
        'sort_order',
        'status',
    ];

    protected static function booted()
    {
        static::saving(fn ($category) => $category->slug = str()->slug($category->name));
    }

    // علاقة القسم بالأب (القسم الرئيسي)
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // علاقة القسم بالأبناء (الأقسام الفرعية)
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}
