<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LearningGroupRequest;
use App\Models\AttendanceRecord;
use App\Models\LearningGroup;
use App\Services\Admin\AdminLearningGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLearningGroupController extends Controller
{
    private AdminLearningGroupService $adminLearningGroupService;

    public function __construct(AdminLearningGroupService $adminLearningGroupService)
    {
        $this->adminLearningGroupService = $adminLearningGroupService;
    }

    public function index(Request $request): JsonResponse
    {
        $groups = $this->adminLearningGroupService->getAllGroups(
            $request->get('perPage', 10),
            $request->get('search')
        );

        return $this->paginateResponse($groups, 'Learning groups retrieved successfully');
    }

    public function store(LearningGroupRequest $request): JsonResponse
    {
        $groupResource = $this->adminLearningGroupService->createGroup($request->validated());

        return $this->successResponse($groupResource, 'Learning group created successfully', 201);
    }

    public function show(LearningGroup $learningGroup): JsonResponse
    {
        $groupResource = $this->adminLearningGroupService->getGroupDetails($learningGroup);

        return $this->successResponse($groupResource, 'Learning group retrieved successfully');
    }

    public function update(LearningGroupRequest $request, LearningGroup $learningGroup): JsonResponse
    {
        $groupResource = $this->adminLearningGroupService->updateGroup($learningGroup, $request->validated());

        return $this->successResponse($groupResource, 'Learning group and students updated successfully');
    }

    public function destroy(LearningGroup $learningGroup): JsonResponse
    {
        $this->adminLearningGroupService->deleteGroup($learningGroup);

        return $this->successResponse(null, 'Learning group deleted successfully');
    }

    public function selection(): JsonResponse
    {
        $groups = $this->adminLearningGroupService->getSelection();

        return $this->successResponse($groups, 'Learning groups retrieved for selection successfully');
    }

    /**
     * Get available students for assignment (GET)
     */
    public function getUnassignedStudents($groupId): JsonResponse
    {
        $result = $this->adminLearningGroupService->getUnassignedCourseStudents((int) $groupId);

        if (!$result['success']) {
            return $this->errorResponse($result['message'], $result['status']);
        }

        return $this->successResponse($result['data'], 'Unassigned students retrieved successfully');
    }

    /**
     * Bulk assign students to the group (POST)
     */
    public function bulkAssignStudents(Request $request, $groupId): JsonResponse
    {
        $request->validate([
            'student_ids'   => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'course_id'     => 'required|exists:courses,id',
        ]);

        $result = $this->adminLearningGroupService->bulkAssignToGroup(
            $request->student_ids,
            (int) $groupId,
            (int) $request->course_id
        );

        if (empty($result['unpaid_students'])) {
            return $this->successResponse(
                null,
                "All selected students ({$result['assigned_count']}) have been assigned to the group successfully."
            );
        }

        return $this->successResponse(
            ['unpaid_students' => $result['unpaid_students']],
            "Successfully assigned {$result['assigned_count']} students. Some students were skipped due to incomplete payment."
        );
    }

    /**
     * Bulk-mark selected students as completed (POST)
     */
    public function bulkCompleteStudents(Request $request, $groupId): JsonResponse
    {
        $request->validate([
            'student_ids'   => 'required|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        $result = $this->adminLearningGroupService->bulkCompleteStudents(
            $request->student_ids,
            (int) $groupId
        );

        if (!$result['success']) {
            return $this->errorResponse($result['message'], $result['status']);
        }

        return $this->successResponse(
            null,
            "Successfully marked {$result['completed_count']} student(s) as completed."
        );
    }

    /**
     * Get a group's weekly schedule
     */
    public function getSchedule(LearningGroup $learningGroup): JsonResponse
    {
        $learningGroup->load('schedules');

        return $this->successResponse($learningGroup->schedules, 'Schedule retrieved successfully');
    }

    /**
     * Get all attendance sessions for a group
     */
    public function getSessions(LearningGroup $learningGroup): JsonResponse
    {
        $sessions = $learningGroup->attendanceSessions()
            ->with('schedule')
            ->orderBy('session_date')
            ->get();

        return $this->successResponse($sessions, 'Sessions retrieved successfully');
    }

    /**
     * Get attendance report for a group (optionally filtered by session)
     */
    public function getAttendanceReport(LearningGroup $learningGroup, Request $request): JsonResponse
    {
        $sessionId = $request->get('session_id');

        $query = AttendanceRecord::whereHas('session', function ($q) use ($learningGroup) {
            $q->where('learning_group_id', $learningGroup->id);
        })->with(['student:id,full_name,phone', 'session:id,session_date,status']);

        if ($sessionId) {
            $query->where('session_id', $sessionId);
        }

        return $this->successResponse($query->get(), 'Attendance report retrieved successfully');
    }
}
