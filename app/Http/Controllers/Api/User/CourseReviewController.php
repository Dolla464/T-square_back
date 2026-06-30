<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\CourseReview\CourseReviewResource;
use App\Services\User\CourseReviewService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

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
     * اخر 5 reviews عموما.
     *
     * GET /api/student/reviews/latest
     */
    public function latest(): JsonResponse
    {
        $reviews = $this->courseReviewService->getLatestReviews();

        return $this->successResponse(
            data: CourseReviewResource::collection($reviews),
            message: 'Latest reviews retrieved successfully.',
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
}
