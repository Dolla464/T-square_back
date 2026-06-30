<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\CourseDashboardRequest;
use App\Http\Resources\User\Courses\CourseDashboardResource;
use App\Models\Student;
use App\Models\User;
use App\Services\User\CourseDashboardService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @tags Courses
 */
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

        // ── The current student logged in ───────────────────────────────────
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {

            return $this->errorResponse('Unauthenticated', 401);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (! $student) {
            return $this->errorResponse('Student not found', 404);
        }

        // ── Get the data from the Service ────────────────────────────────────
        $data = $this->dashboardService->getDashboardData(
            studentId: $student->id,
            filters: [
                'search' => $validated['search'] ?? null,
                'status' => $validated['status'] ?? 'all',
            ]
        );

        // ── Prepare the Response ─────────────────────────────────────────────
        return $this->successResponse(
            data: [
                'stats' => $data['stats'],
                'courses' => CourseDashboardResource::collection($data['courses']),
            ],
            message: 'Dashboard data fetched successfully',
        );
    }

    /**
     * GET /api/student/courses/{id}
     * Get the details of a specific course fully for the selected student
     */
    public function showCourse(int $id, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (! $student) {
            return $this->errorResponse('Student not found', 404);
        }

        try {
            // Call the new method from the Service
            $course = $this->dashboardService->getCourseDetails($student->id, $id);

            return $this->successResponse(
                data: new CourseDashboardResource($course),
                message: 'Course details fetched successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Course not found or access denied', 404);
        }
    }
}
