<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Admin: Dashboard
 */
class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
    ) {}

    /**
     * GET /api/admin/dashboard/stats
     */
    public function getStats(): JsonResponse
    {
        return $this->successResponse(
            $this->dashboardService->getStats(),
            'Dashboard stats retrieved successfully'
        );
    }

    /**
     * GET /api/admin/dashboard/revenue-chart?period=week|month|year
     */
    public function getRevenueChart(Request $request): JsonResponse
    {
        $period = $request->query('period', 'month');

        return $this->successResponse(
            $this->dashboardService->getRevenueChart($period),
            'Revenue chart retrieved successfully'
        );
    }

    /**
     * GET /api/admin/dashboard/course-sales?period=week|month|year
     */
    public function getCourseSales(Request $request): JsonResponse
    {
        $period = $request->query('period', 'month');

        return $this->successResponse(
            $this->dashboardService->getCourseSales($period),
            'Course sales chart retrieved successfully'
        );
    }

    /**
     * GET /api/admin/dashboard/recent-enrollments?limit=4
     */
    public function getRecentEnrollments(Request $request): JsonResponse
    {
        $limit = min(20, max(1, (int) $request->query('limit', 4)));

        return $this->successResponse(
            $this->dashboardService->getRecentEnrollments($limit),
            'Recent enrollments retrieved successfully'
        );
    }

    /**
     * GET /api/admin/dashboard/recent-orders?limit=4
     */
    public function getRecentOrders(Request $request): JsonResponse
    {
        $limit = min(20, max(1, (int) $request->query('limit', 4)));

        return $this->successResponse(
            $this->dashboardService->getRecentOrders($limit),
            'Recent orders retrieved successfully'
        );
    }

    /**
     * GET /api/admin/dashboard/top-courses?limit=3
     */
    public function getTopCourses(Request $request): JsonResponse
    {
        $limit = min(20, max(1, (int) $request->query('limit', 3)));

        return $this->successResponse(
            $this->dashboardService->getTopCourses($limit),
            'Top courses retrieved successfully'
        );
    }
}
