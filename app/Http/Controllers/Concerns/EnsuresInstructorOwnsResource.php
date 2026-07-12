<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Question;
use Illuminate\Http\JsonResponse;

trait EnsuresInstructorOwnsResource
{
    protected function resolveInstructor($request): ?Instructor
    {
        return $request->user()?->instructor;
    }

    protected function instructorNotFoundResponse(): JsonResponse
    {
        return $this->errorResponse('Instructor profile not found.', 404);
    }

    protected function accessDeniedResponse(): JsonResponse
    {
        return $this->errorResponse('Access denied. You do not own this resource.', 403);
    }

    protected function verifyGroupOwnership(LearningGroup $group, Instructor $instructor): ?JsonResponse
    {
        $group->loadMissing('course.instructors', 'courseInstructor');

        if ($group->courseInstructor?->instructor_id === $instructor->id) {
            return null;
        }

        if ($group->course?->hasInstructor($instructor->id)) {
            return null;
        }

        return $this->accessDeniedResponse();
    }

    protected function verifyExamOwnership(Exam $exam, Instructor $instructor): ?JsonResponse
    {
        $exam->loadMissing('course.instructors');

        if (! $exam->course || ! $exam->course->hasInstructor($instructor->id)) {
            return $this->accessDeniedResponse();
        }

        return null;
    }

    protected function verifyExamBelongsToGroup(Exam $exam, LearningGroup $group): ?JsonResponse
    {
        if ((int) $exam->course_id !== (int) $group->course_id) {
            return $this->errorResponse('Exam not found for this group.', 404);
        }

        return null;
    }

    protected function verifyQuestionOwnership(Question $question, Instructor $instructor): ?JsonResponse
    {
        $question->loadMissing('exam.course.instructors');

        if (! $question->exam || ! $question->exam->course || ! $question->exam->course->hasInstructor($instructor->id)) {
            return $this->accessDeniedResponse();
        }

        return null;
    }

    protected function verifyCourseBelongsToInstructor(int $courseId, Instructor $instructor): ?JsonResponse
    {
        $ownsCourse = $instructor->assignedCourses()->whereKey($courseId)->exists();

        if (! $ownsCourse) {
            return $this->accessDeniedResponse();
        }

        return null;
    }
}
