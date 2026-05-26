<?php

namespace App\Http\Controllers\Api\User;

use App\Events\StudentEnrolled;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\StoreEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\User\EnrollmentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly EnrollmentService $enrollmentService,
    ) {}

    /**
     * POST /api/student/enrollments
     */
    public function store(StoreEnrollmentRequest $request): JsonResponse
    {
        $payload = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $student = $user->student;
        if (! $student) {
            return $this->errorResponse(
                message: 'Only students can enroll in courses.',
                code: 403
            );
        }

        $result = $this->enrollmentService->enroll($student, $payload);
        $enrollment = $result['enrollment']->loadMissing(['course', 'student.user']);

        StudentEnrolled::dispatch(
            student: $enrollment->student,
            course: $enrollment->course,
            enrollment: $enrollment,
        );

        return $this->successResponse(
            data: [
                'enrollment' => $enrollment,
                'order' => $result['order'],
                'whatsapp_link' => $result['whatsapp_link'],
            ],
            message: 'Enrollment created successfully.',
            code: 201,
        );
    }

    /**
     * GET /api/courses/{course_id}/check-enrollment
     * Check if the student is enrolled in the course
     */
    public function checkEnrollment($courseId): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $student = $user->student;

        if (!$student) {
            return $this->errorResponse(
                message: 'Only students can check enrollment status.',
                code: 403
            );
        }

        // Check directly in the enrollments table using the course_id from the path
        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $courseId)
            ->first();

        $isEnrolled = false;

        if ($enrollment) {
            // Check if the course is paid and has an order_id
            if ($enrollment->order_id) {
                $isEnrolled = $enrollment->order?->status === 'completed';
            } else {
                // Free course, if the enrollment exists, it means the student is enrolled and accepted automatically
                $isEnrolled = true;
            }
        }

        return $this->successResponse(
            data: [
                'is_enrolled' => $isEnrolled
            ],
            message: 'Enrollment status checked successfully.'
        );
    }
}
