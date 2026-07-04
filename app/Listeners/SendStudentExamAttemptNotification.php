<?php

namespace App\Listeners;

use App\Events\StudentExamAttemptCompleted;
use App\Models\Enrollment;
use App\Notifications\InstructorExamResultNotification;
use App\Notifications\StudentExamAttemptStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendStudentExamAttemptNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(StudentExamAttemptCompleted $event): void
    {
        $attempt = $event->attempt;

        // Notify the student
        $studentUser = $attempt->student?->user;
        if ($studentUser) {
            $studentUser->notify(new StudentExamAttemptStatusNotification($attempt));
        }

        // Notify the instructor
        $this->notifyInstructor($attempt);
    }

    private function notifyInstructor(mixed $attempt): void
    {
        $attempt->loadMissing(['student', 'exam.course.instructor.user']);

        $courseId  = $attempt->exam?->course_id;
        $studentId = $attempt->student_id;

        // Resolve group and instructor from the student's enrollment
        $enrollment = $courseId && $studentId
            ? Enrollment::with('learningGroup.instructor.user')
                ->where('student_id', $studentId)
                ->where('course_id', $courseId)
                ->first()
            : null;

        $groupName       = $enrollment?->learningGroup?->group_name;
        $instructorUser  = $enrollment?->learningGroup?->instructor?->user
            ?? $attempt->exam?->course?->instructor?->user;

        if (! $instructorUser) {
            return;
        }

        $instructorUser->notify(new InstructorExamResultNotification($attempt, $groupName));
    }
}
