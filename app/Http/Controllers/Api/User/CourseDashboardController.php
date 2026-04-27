<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\Courses\CourseDashboardResource;
use App\Services\User\CourseDashboardService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Website\CourseDashboardRequest as Request;


class CourseDashboardController extends Controller
{
    public function __construct(
        private readonly CourseDashboardService $dashboardService
    ) {}
 
    /**
     * GET /api/student/courses/dashboard
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Validation
        $validated = $request->validated();
 
        // ── الطالب الحالي المسجل دخوله ───────────────────────────────────
        /** @var \App\Models\Student $student */
        $student = $request->user();
 
        // ── جلب البيانات من الـ Service ────────────────────────────────────
        $data = $this->dashboardService->getDashboardData(
            studentId: $student->id,
            filters: [
                'search' => $validated['search'] ?? null,
                'status' => $validated['status'] ?? 'all',
            ]
        );
 
        // ── تجهيز الـ Response ─────────────────────────────────────────────
        return response()->json([
            'success' => true,
            'data'    => [
                'stats'   => $data['stats'],
                'courses' => CourseDashboardResource::collection($data['courses']),
            ],
        ]);
    }
}
