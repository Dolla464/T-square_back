<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Concerns\EnsuresInstructorOwnsResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\GradeExamAttemptRequest;
use App\Http\Resources\User\Exam\ExamAttemptReviewResource;
use App\Models\ExamAttempt;
use App\Services\Exam\ExamGradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Instructor: Exam Grading
 */
class InstructorExamGradingController extends Controller
{
    use EnsuresInstructorOwnsResource;

    public function __construct(
        private ExamGradingService $examGradingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (! $instructor) {
            return $this->instructorNotFoundResponse();
        }

        $attempts = $this->examGradingService->getPendingGradingForInstructor($instructor->id);

        return $this->successResponse(
            $attempts->values()->all(),
            'Pending grading attempts retrieved successfully'
        );
    }

    public function show(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (! $instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyAttemptOwnership($attempt, $instructor)) {
            return $response;
        }

        $reviewAttempt = $this->examGradingService->getAttemptForGrading($attempt);

        return $this->successResponse(
            new ExamAttemptReviewResource($reviewAttempt),
            'Attempt grading details retrieved successfully'
        );
    }

    public function grade(GradeExamAttemptRequest $request, ExamAttempt $attempt): JsonResponse
    {
        $instructor = $this->resolveInstructor($request);
        if (! $instructor) {
            return $this->instructorNotFoundResponse();
        }

        if ($response = $this->verifyAttemptOwnership($attempt, $instructor)) {
            return $response;
        }

        $result = $this->examGradingService->gradeAttempt(
            $attempt->id,
            $request->validated('answers'),
            $instructor->id,
        );

        return $this->successResponse(
            $result,
            'Exam attempt graded and finalized successfully'
        );
    }
}
