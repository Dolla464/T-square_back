<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\GetScheduleRequest;
use App\Models\LearningGroup;
use App\Services\Instructor\InstructorDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Instructor: Dashboard
 */
class InstructorDashboardController extends Controller
{
    public function __construct(private InstructorDashboardService $dashboardService)
    {}

    /**
     * GET /api/instructor/dashboard/stats
     * Returns 4 overview widgets: total groups, active, completed, total students.
     */
    public function getStats(Request $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        $stats = $this->dashboardService->getStats($instructor->id);

        return $this->successResponse($stats, 'Dashboard stats retrieved successfully');
    }

    /**
     * GET /api/instructor/dashboard/active-groups
     * Returns the table of active groups with completion percentages.
     */
    public function getActiveGroups(Request $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        $groups = $this->dashboardService->getActiveGroups($instructor->id);

        return $this->successResponse($groups, 'Active groups retrieved successfully');
    }

    /**
     * GET /api/instructor/dashboard/completed-groups
     * Returns paginated completed groups.
     * Query params: page (default 1), per_page (default 10, max 50)
     */
    public function getCompletedGroups(Request $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        $page    = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(1, (int) $request->input('per_page', 10)));

        $result = $this->dashboardService->getCompletedGroups($instructor->id, $page, $perPage);

        return $this->successResponse($result, 'Completed groups retrieved successfully');
    }

    /**
     * GET /api/instructor/dashboard/groups/{group}
     * Returns full details for a single group: chart data + students attendance.
     */
    public function getGroupDetails(Request $request, LearningGroup $group): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        $group->loadMissing('course.instructors', 'courseInstructor');

        $allowed = $group->courseInstructor?->instructor_id === $instructor->id
            || $group->course?->hasInstructor($instructor->id);

        if (! $allowed) {
            return $this->errorResponse('Access denied. You do not own this group.', 403);
        }

        $details = $this->dashboardService->getGroupDetails($group);

        return $this->successResponse($details, 'Group details retrieved successfully');
    }

    /**
     * GET /api/instructor/dashboard/schedule
     * Returns sessions for a given date (default: current week).
     * Query param: date (optional, Y-m-d)
     */
    public function getSchedule(GetScheduleRequest $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        $schedule = $this->dashboardService->getSchedule(
            $instructor->id,
            $request->input('date')
        );

        return $this->successResponse($schedule, 'Schedule retrieved successfully');
    }
}
