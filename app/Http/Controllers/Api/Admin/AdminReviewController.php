<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminReviewRequest;
use App\Http\Resources\Admin\AdminReviewCollection;
use App\Http\Resources\Admin\AdminReviewResource;
use App\Models\CourseReview;
use App\Services\Admin\AdminReviewService;
use App\Services\Admin\AdminReviewAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminReviewController extends Controller
{
    public function __construct(
        private readonly AdminReviewService $reviewService,
        private readonly AdminReviewAnalyticsService $analytics
    ) {}

    /**
     * Display a listing of the reviews.
     */
    public function index(Request $request): AdminReviewCollection
    {
        // 1. جلب الفلاتر المتاحة من الطلب بالكامل لضمان عدم سقوط أي فلتر (مثل review_status)
        $filters = $request->only(['search', 'review_status']);

        $reviews = $this->reviewService->index(
            $request->query('per_page', 10),
            $filters
        );

        // 2. جلب الإحصائيات المكيشة
        $stats = $this->analytics->getRecentStats();

        // 3. تمرير الـ Paginator مباشرة، واستخدام additional لإرفاق الـ Meta Data (الإحصائيات) نظيفة في الـ JSON
        return (new AdminReviewCollection($reviews))
            ->additional([
                'analytics' => $stats
            ]);
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

        Cache::forget('admin_dashboard_review_stats');

        return $this->successResponse(
            new AdminReviewResource($updatedReview),
            'Review updated successfully'
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
