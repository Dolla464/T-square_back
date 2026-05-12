<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'certificate_url',
        'certificate_num',
        'issued_at',
    ];

    // الشهادة تخص طالب واحد
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // الشهادة تخص كورس واحد
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
