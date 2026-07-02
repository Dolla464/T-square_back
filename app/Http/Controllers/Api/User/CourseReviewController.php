<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\StoreCourseReviewRequest;
use App\Http\Resources\User\CourseReview\CourseReviewResource;
use App\Http\Resources\User\CourseReview\StudentSubmittedReviewResource;
use App\Services\User\CourseReviewService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @tags Reviews
 */
class CourseReviewController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CourseReviewService $courseReviewService,
    ) {}

    /**
     * Latest 5 accepted public reviews with rating >= 4.
     *
     * GET /api/student/reviews/latest
     */
    public function latest(): JsonResponse
    {
        $reviews = $this->courseReviewService->getPublicFeaturedReviews();

        return $this->successResponse(
            data: CourseReviewResource::collection($reviews)->resolve(),
            message: 'Featured reviews retrieved successfully.',
        );
    }

    /**
     * Reviews الخاصة بكورس معين.
     *
     * GET /api/student/reviews/course/{courseId}?limit=10
     */
    public function course(int $courseId): JsonResponse
    {
        $reviews = $this->courseReviewService->getCourseReviews($courseId);

        return $this->successResponse(
            data: CourseReviewResource::collection($reviews),
            message: 'Course reviews retrieved successfully.',
        );
    }

    /**
     * Submit a course review (authenticated student).
     *
     * POST /api/student/reviews
     */
    public function store(StoreCourseReviewRequest $request): JsonResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            return $this->errorResponse('Student profile not found.', 404);
        }

        try {
            $result = $this->courseReviewService->submitStudentReview(
                $student,
                $request->validated()
            );
        } catch (UnprocessableEntityHttpException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (ConflictHttpException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        return $this->successResponse(
            data: new StudentSubmittedReviewResource($result['review']),
            message: $result['certificate_issued']
                ? 'Review submitted successfully. Your certificate is now available.'
                : 'Review submitted successfully.',
            code: 201,
        );
    }

    /**
     * Check whether the authenticated student can submit a review for a course.
     *
     * GET /api/student/reviews/eligibility/{courseId}
     */
    public function eligibility(Request $request, int $courseId): JsonResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            return $this->errorResponse('Student profile not found.', 404);
        }

        return $this->successResponse(
            data: $this->courseReviewService->getReviewEligibility($student, $courseId),
            message: 'Review eligibility retrieved successfully.',
        );
    }
}
