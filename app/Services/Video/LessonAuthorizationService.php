<?php

namespace App\Services\Video;

use App\DTO\AuthorizationResult;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;

class LessonAuthorizationService
{
    public function authorize(User $user, Lesson $lesson): AuthorizationResult
    {
        if (! $user->student) {
            return AuthorizationResult::forbidden('Student profile not found.');
        }

        $lesson->loadMissing('course');

        if (! $lesson->course || $lesson->course->status !== 'published') {
            return AuthorizationResult::notFound('Course not found.');
        }

        if (! $lesson->is_active) {
            return AuthorizationResult::forbidden('This lesson is not available.');
        }

        $course = Course::active()->find($lesson->course_id);

        if (! $course) {
            return AuthorizationResult::notFound('Course not found.');
        }

        $studentId = $user->student->id;

        $enrollment = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('course_id', $course->id)
            ->first();

        if (! $enrollment) {
            return AuthorizationResult::forbidden('You are not enrolled in this course.');
        }

        if ($enrollment->order_id) {
            $enrollment->loadMissing('order');
            if ($enrollment->order?->status !== 'completed') {
                return AuthorizationResult::forbidden('Course payment is not completed.');
            }
        }

        if (! $lesson->hasGoogleDriveVideo()) {
            return AuthorizationResult::unprocessable('This lesson has no available video.');
        }

        if (! $course->google_storage_account_id) {
            return AuthorizationResult::unprocessable('Video is not configured for this course.');
        }

        return AuthorizationResult::allowed();
    }
}
