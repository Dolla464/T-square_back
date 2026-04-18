<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Solution extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description'
    ];

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'solution_tag');
    }
}
