<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\CourseDashboardRequest;
use App\Http\Resources\User\Courses\CourseDashboardResource;
use App\Models\User;
use App\Services\User\CourseDashboardService;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponseTrait;

class CourseDashboardController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CourseDashboardService $dashboardService
    ) {}
 
    /**
     * GET /api/student/courses/dashboard
     */
    public function __invoke(CourseDashboardRequest $request): JsonResponse
    {
        $validated = $request->validated();
 
        // ── الطالب الحالي المسجل دخوله ───────────────────────────────────
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $student = $user->student;
        if (! $student) {
            return $this->errorResponse('Student not found', 404);
        }
 
        // ── جلب البيانات من الـ Service ────────────────────────────────────
        $data = $this->dashboardService->getDashboardData(
            studentId: $student->id,
            filters: [
                'search' => $validated['search'] ?? null,
                'status' => $validated['status'] ?? 'all',
            ]
        );
 
        // ── تجهيز الـ Response ─────────────────────────────────────────────
        return $this->successResponse([
            'stats' => $data['stats'],
            'courses' => CourseDashboardResource::collection($data['courses']),
        ], 'Dashboard data fetched successfully');
    }
}
