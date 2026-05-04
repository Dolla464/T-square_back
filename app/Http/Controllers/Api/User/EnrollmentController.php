<?php

namespace App\Http\Controllers\Api\User;

use App\Events\StudentEnrolled;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\StoreEnrollmentRequest;
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

        /** @var \App\Models\User $user */
        $user = $request->user();

        $student = $user->student;

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
}

