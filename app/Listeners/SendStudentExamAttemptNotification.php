<?php

namespace App\Listeners;

use App\Events\StudentExamAttemptCompleted;
use App\Models\Course;
use App\Models\Enrollment;
use App\Notifications\CourseReviewRequired;
use App\Notifications\InstructorExamResultNotification;
use App\Notifications\StudentExamAttemptStatusNotification;
use App\Support\CourseInstructorNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendStudentExamAttemptNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(StudentExamAttemptCompleted $event): void
    {
        $attempt = $event->attempt;

        $studentUser = $attempt->student?->user;
        if ($studentUser) {
            $studentUser->notify(new StudentExamAttemptStatusNotification($attempt));
        }

        $this->notifyInstructors($attempt);

        if ($attempt->status === 'passed' && $attempt->exam?->is_final) {
            $this->notifyStudentReviewRequired($attempt);
        }
    }

    private function notifyInstructors(mixed $attempt): void
    {
        $attempt->loadMissing(['student', 'exam.course.instructors.user']);

        $courseId = $attempt->exam?->course_id;
        $studentId = $attempt->student_id;

        $enrollment = $courseId && $studentId
            ? Enrollment::with('learningGroup.courseInstructor.instructor.user')
                ->where('student_id', $studentId)
                ->where('course_id', $courseId)
                ->first()
            : null;

        $groupName = $enrollment?->learningGroup?->group_name;
        $notification = new InstructorExamResultNotification($attempt, $groupName);

        $course = $attempt->exam?->course;
        if ($course instanceof Course) {
            CourseInstructorNotifier::notifyAll($course, $notification);
            return;
        }

        $instructorUser = $enrollment?->learningGroup?->courseInstructor?->instructor?->user;
        if ($instructorUser) {
            $instructorUser->notify($notification);
        }
    }

    private function notifyStudentReviewRequired(mixed $attempt): void
    {
        $attempt->loadMissing(['student.user', 'exam']);

        $courseId = $attempt->exam?->course_id;
        $studentId = $attempt->student_id;

        if (! $courseId || ! $studentId) {
            return;
        }

        $studentUser = $attempt->student?->user;
        if (! $studentUser) {
            return;
        }

        $enrollment = Enrollment::with('learningGroup')
            ->where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->first();

        if (! $enrollment) {
            return;
        }

        $studentUser->notify(new CourseReviewRequired($enrollment, $enrollment->learningGroup));
    }
}
