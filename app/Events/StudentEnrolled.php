<?php

namespace App\Events;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentEnrolled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Student $student,
        public readonly Course $course,
        public readonly Enrollment $enrollment,
    ) {}
}
