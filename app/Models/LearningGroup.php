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

    /**
     * Students enrolled in this group, reached through the enrollments pivot.
     *
     * SQL produced:
     *   SELECT students.*
     *   FROM students
     *   INNER JOIN enrollments ON enrollments.student_id = students.id
     *   WHERE enrollments.group_id = ?
     */
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            Enrollment::class,
            'group_id',    // FK on enrollments referencing learning_groups.id
            'id',          // PK on students (joined via enrollments.student_id)
            'id',          // PK on learning_groups
            'student_id'   // FK on enrollments referencing students.id
        );
    }
}
