<?php

namespace App\Listeners;

use App\Events\StudentExamAttemptAwaitingGrading;
use App\Models\Course;
use App\Models\Enrollment;
use App\Notifications\InstructorGradingRequiredNotification;
use App\Support\CourseInstructorNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendInstructorGradingRequiredNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(StudentExamAttemptAwaitingGrading $event): void
    {
        $attempt = $event->attempt;
        $attempt->loadMissing(['student', 'exam.course.instructors.user']);

        $courseId = $attempt->exam?->course_id;
        $studentId = $attempt->student_id;

        $enrollment = $courseId && $studentId
            ? Enrollment::with('learningGroup')
                ->where('student_id', $studentId)
                ->where('course_id', $courseId)
                ->first()
            : null;

        $groupName = $enrollment?->learningGroup?->group_name;
        $notification = new InstructorGradingRequiredNotification($attempt, $groupName);

        $course = $attempt->exam?->course;
        if ($course instanceof Course) {
            CourseInstructorNotifier::notifyAll($course, $notification);
        }
    }
}
