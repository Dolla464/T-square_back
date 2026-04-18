<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningGroup extends Model
{
    use HasFactory;

    protected $fillable = ['group_name', 'course_id', 'instructor_id'];

    // المجموعة تنتمي لكورس واحد
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // المجموعة تابعة لمدرب واحد
    public function instructor()
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'group_id');
    }
}
