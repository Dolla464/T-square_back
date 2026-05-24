<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminReviewRequest;
use App\Http\Resources\Admin\AdminReviewResource;
use App\Models\CourseReview;
use App\Services\Admin\AdminReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    private AdminReviewService $reviewService;

    public function __construct(AdminReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * Display a listing of the reviews.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
        ];

        $reviews = $this->reviewService->index(
            $request->query('per_page', 10),
            $filters
        );

        return $this->paginateResponse($reviews->through(function ($review) {
            return new AdminReviewResource($review);
        }), 'Reviews retrieved successfully');
    }

    /**
     * Display the specified review.
     */
    public function show(CourseReview $review): JsonResponse
    {
        $review = $this->reviewService->show($review);

        return $this->successResponse(
            new AdminReviewResource($review),
            'Review retrieved successfully'
        );
    }

    /**
     * Update the specified review in storage.
     */
    public function update(UpdateAdminReviewRequest $request, CourseReview $review): JsonResponse
    {
        $updatedReview = $this->reviewService->update(
            $review,
            
            $request->validated()
        );

        return $this->successResponse(
            data: new AdminReviewResource($updatedReview),
            message: 'Review updated successfully'
        );
    }

    /**
     * Remove the specified review from storage.
     */
    public function destroy(CourseReview $review): JsonResponse
    {
        $this->reviewService->destroy($review);

        return $this->successResponse(
            null,
            'Review deleted successfully'
        );
    }
}
