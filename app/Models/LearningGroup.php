<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_name',
        'course_id',
        'course_instructor_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function courseInstructor(): BelongsTo
    {
        return $this->belongsTo(CourseInstructor::class);
    }

    public function getInstructorAttribute(): ?Instructor
    {
        $this->loadMissing('courseInstructor.instructor');

        return $this->courseInstructor?->instructor;
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LearningGroupSchedule::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    /**
     * Students enrolled in this group, reached through the enrollments pivot.
     */
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            Enrollment::class,
            'group_id',
            'id',
            'id',
            'student_id'
        );
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'group_id');
    }

    public function activatedExams()
    {
        return $this->belongsToMany(Exam::class, 'group_exam_activations')
            ->withPivot(['activated_by', 'activated_at']);
    }

    public function examActivations(): HasMany
    {
        return $this->hasMany(GroupExamActivation::class);
    }

    public function getInstructorIdAttribute(): ?int
    {
        return $this->courseInstructor?->instructor_id;
    }

    public function scopeForInstructor(Builder $query, int $instructorId): Builder
    {
        return $query->whereHas(
            'courseInstructor',
            fn ($q) => $q->where('instructor_id', $instructorId)
        );
    }
}
