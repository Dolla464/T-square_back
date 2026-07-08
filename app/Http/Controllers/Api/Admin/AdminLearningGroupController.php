<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminMarkAttendanceRequest;
use App\Http\Requests\Admin\LearningGroupRequest;
use App\Http\Resources\User\Exam\ExamResultResource;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Exam;
use App\Models\LearningGroup;
use App\Models\Student;
use App\Services\Admin\AdminLearningGroupService;
use App\Services\Attendance\AttendanceSessionService;
use App\Services\Attendance\GroupAttendanceSummaryService;
use App\Services\Exam\GroupExamResultsService;
use App\Events\StudentScanned;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @tags Admin: Learning Groups
 */
class AdminLearningGroupController extends Controller
{
    private AdminLearningGroupService $adminLearningGroupService;

    public function __construct(
        AdminLearningGroupService $adminLearningGroupService,
        private AttendanceSessionService $attendanceSessionService,
        private GroupAttendanceSummaryService $groupAttendanceSummaryService,
        private GroupExamResultsService $groupExamResultsService
    ) {
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

    public function selection(Request $request): JsonResponse
    {
        $courseId = $request->query('course_id');
        $status = $request->query('status');

        $groups = $this->adminLearningGroupService->getSelection(
            $courseId !== null && $courseId !== '' ? (int) $courseId : null,
            $status !== null && $status !== '' ? (string) $status : null
        );

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
     * GET /api/admin/learning-groups/{learningGroup}/students/export
     *
     * Query params: format=pdf|excel
     */
    public function exportStudents(Request $request, LearningGroup $learningGroup): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'nullable|string|in:pdf,excel',
        ]);

        $format = $validated['format'] ?? 'pdf';
        $payload = $this->adminLearningGroupService->getGroupStudentsForExport($learningGroup);

        if ($format === 'excel') {
            return $this->exportStudentsExcel($payload['group'], $payload['students']);
        }

        return $this->exportStudentsPdf($payload['group'], $payload['students']);
    }

    private function exportStudentsPdf(LearningGroup $group, Collection $students): JsonResponse
    {
        $pdf = Pdf::loadView('exports.group-students-pdf', [
            'group'       => $group,
            'students'    => $students,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'group-students-' . $group->id . '-' . now()->format('Ymd') . '.pdf';

        return $this->successResponse([
            'content'  => base64_encode($pdf->output()),
            'filename' => $filename,
            'mime'     => 'application/pdf',
        ], 'PDF export ready');
    }

    private function exportStudentsExcel(LearningGroup $group, Collection $students): JsonResponse
    {
        $filename = 'group-students-' . $group->id . '-' . now()->format('Ymd') . '.csv';

        ob_start();
        $handle = fopen('php://output', 'w');

        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['#', 'Student', 'Email', 'Phone', 'Status']);

        foreach ($students as $idx => $student) {
            fputcsv($handle, [
                $idx + 1,
                $student->full_name ?? '',
                $student->email ?? '',
                $student->phone ?? '',
                $student->is_completed ? 'Completed' : 'In Progress',
            ]);
        }

        fclose($handle);
        $content = ob_get_clean();

        return $this->successResponse([
            'content'  => base64_encode($content),
            'filename' => $filename,
            'mime'     => 'text/csv',
        ], 'CSV export ready');
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
            ->get()
            ->transform(function ($session) {
                $session->session_date = $session->session_date?->format('Y-m-d');
                $session->override_date = $session->override_date?->format('Y-m-d');

                return $session;
            });

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

    /**
     * GET /api/admin/learning-groups/{learningGroup}/sessions/{session}/attendance
     */
    public function getSessionAttendance(LearningGroup $learningGroup, AttendanceSession $session): JsonResponse
    {
        if (!$this->attendanceSessionService->assertSessionBelongsToGroup($session, $learningGroup)) {
            return $this->errorResponse('Session not found for this group.', 404);
        }

        return $this->successResponse(
            $this->attendanceSessionService->getSessionDetails($session),
            'Session attendance retrieved successfully'
        );
    }

    /**
     * POST /api/admin/learning-groups/{learningGroup}/sessions/{session}/attendance/mark
     */
    public function markSessionAttendance(
        AdminMarkAttendanceRequest $request,
        LearningGroup $learningGroup,
        AttendanceSession $session
    ): JsonResponse {
        if (! $this->attendanceSessionService->assertSessionBelongsToGroup($session, $learningGroup)) {
            return $this->errorResponse('Session not found for this group.', 404);
        }

        $this->attendanceSessionService->assertAdminCanMarkHistoricalSession($session);

        $studentId = (int) $request->validated('student_id');
        $isEnrolled = $learningGroup->students()->where('students.id', $studentId)->exists();

        if (! $isEnrolled) {
            return $this->errorResponse('Student is not enrolled in this group.', 422);
        }

        $record = AttendanceRecord::updateOrCreate(
            [
                'session_id' => $session->id,
                'student_id' => $studentId,
            ],
            [
                'status'    => $request->validated('status'),
                'marked_by' => 'admin_manual',
                'marked_at' => Carbon::now(),
                'notes'     => $request->validated('notes'),
            ]
        );

        $record->load(['session.learningGroup', 'student.user']);
        broadcast(new StudentScanned($record))->toOthers();

        return $this->successResponse([
            'record_id'  => $record->id,
            'session_id' => $record->session_id,
            'student_id' => $record->student_id,
            'status'     => $record->status,
            'marked_at'  => $record->marked_at->toDateTimeString(),
            'marked_by'  => $record->marked_by,
        ], 'Attendance marked successfully');
    }

    /**
     * GET /api/admin/learning-groups/{learningGroup}/attendance-summary
     */
    public function getAttendanceSummary(LearningGroup $learningGroup): JsonResponse
    {
        return $this->successResponse(
            $this->groupAttendanceSummaryService->getGroupDetails($learningGroup),
            'Attendance summary retrieved successfully'
        );
    }

    /**
     * GET /api/admin/learning-groups/{learningGroup}/sessions/{session}/attendance/export
     */
    public function exportSessionAttendance(Request $request, LearningGroup $learningGroup, AttendanceSession $session): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'nullable|string|in:pdf,excel',
        ]);

        if (!$this->attendanceSessionService->assertSessionBelongsToGroup($session, $learningGroup)) {
            return $this->errorResponse('Session not found for this group.', 404);
        }

        $format  = $validated['format'] ?? 'pdf';
        $payload = $this->attendanceSessionService->getSessionDetails($session);

        if ($format === 'excel') {
            return $this->exportSessionAttendanceExcel($payload);
        }

        return $this->exportSessionAttendancePdf($payload);
    }

    /**
     * GET /api/admin/learning-groups/{learningGroup}/students/{student}/attendance
     */
    public function getStudentCourseAttendance(LearningGroup $learningGroup, Student $student): JsonResponse
    {
        try {
            $payload = $this->groupAttendanceSummaryService->getStudentCourseAttendance($learningGroup, $student);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(
            $payload,
            'Student course attendance retrieved successfully'
        );
    }

    /**
     * GET /api/admin/learning-groups/{learningGroup}/students/{student}/attendance/export
     */
    public function exportStudentCourseAttendance(Request $request, LearningGroup $learningGroup, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'nullable|string|in:pdf,excel',
        ]);

        $format  = $validated['format'] ?? 'pdf';

        try {
            $payload = $this->groupAttendanceSummaryService->getStudentCourseAttendance($learningGroup, $student);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        if ($format === 'excel') {
            return $this->exportStudentCourseAttendanceExcel($payload);
        }

        return $this->exportStudentCourseAttendancePdf($payload);
    }

    private function exportSessionAttendancePdf(array $payload): JsonResponse
    {
        $pdf = Pdf::loadView('exports.session-attendance-pdf', [
            'payload'     => $payload,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'session-attendance-' . $payload['session_id'] . '-' . now()->format('Ymd') . '.pdf';

        return $this->successResponse([
            'content'  => base64_encode($pdf->output()),
            'filename' => $filename,
            'mime'     => 'application/pdf',
        ], 'PDF export ready');
    }

    private function exportSessionAttendanceExcel(array $payload): JsonResponse
    {
        $filename = 'session-attendance-' . $payload['session_id'] . '-' . now()->format('Ymd') . '.csv';

        ob_start();
        $handle = fopen('php://output', 'w');
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['#', 'Student Name', 'Email', 'Attendance Status']);

        foreach ($payload['students'] as $idx => $student) {
            fputcsv($handle, [
                $idx + 1,
                $student['full_name'] ?? '',
                $student['email'] ?? '',
                $student['status'] ?? 'not_marked',
            ]);
        }

        fclose($handle);
        $content = ob_get_clean();

        return $this->successResponse([
            'content'  => base64_encode($content),
            'filename' => $filename,
            'mime'     => 'text/csv',
        ], 'CSV export ready');
    }

    private function exportStudentCourseAttendancePdf(array $payload): JsonResponse
    {
        $pdf = Pdf::loadView('exports.student-course-attendance-pdf', [
            'payload'     => $payload,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'student-attendance-' . $payload['student_id'] . '-' . now()->format('Ymd') . '.pdf';

        return $this->successResponse([
            'content'  => base64_encode($pdf->output()),
            'filename' => $filename,
            'mime'     => 'application/pdf',
        ], 'PDF export ready');
    }

    private function exportStudentCourseAttendanceExcel(array $payload): JsonResponse
    {
        $filename = 'student-attendance-' . $payload['student_id'] . '-' . now()->format('Ymd') . '.csv';

        ob_start();
        $handle = fopen('php://output', 'w');
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Student',
            'Email',
            'Group',
            'Course',
            'Attended Sessions',
            'Total Sessions',
            'Attendance %',
        ]);
        fputcsv($handle, [
            $payload['full_name'] ?? '',
            $payload['email'] ?? '',
            $payload['group_name'] ?? '',
            $payload['course_title'] ?? '',
            $payload['attended_sessions'] ?? 0,
            $payload['total_sessions'] ?? 0,
            ($payload['attendance_percentage'] ?? 0) . '%',
        ]);

        fputcsv($handle, []);
        fputcsv($handle, ['Session Date', 'Start', 'End', 'Session Status', 'Attendance Status']);

        foreach ($payload['sessions'] as $session) {
            fputcsv($handle, [
                $session['session_date'] ?? '',
                $session['start_time'] ?? '',
                $session['end_time'] ?? '',
                $session['session_status'] ?? '',
                $session['status'] ?? 'not_marked',
            ]);
        }

        fclose($handle);
        $content = ob_get_clean();

        return $this->successResponse([
            'content'  => base64_encode($content),
            'filename' => $filename,
            'mime'     => 'text/csv',
        ], 'CSV export ready');
    }

    /**
     * GET /api/admin/learning-groups/{learningGroup}/exams
     */
    public function getGroupExams(LearningGroup $learningGroup): JsonResponse
    {
        return $this->successResponse(
            $this->groupExamResultsService->getExamsForGroup($learningGroup),
            'Group exams retrieved successfully'
        );
    }

    /**
     * GET /api/admin/learning-groups/{learningGroup}/exams/{exam}/results
     */
    public function getExamResults(LearningGroup $learningGroup, Exam $exam): JsonResponse
    {
        try {
            $payload = $this->groupExamResultsService->getExamResultsSummary($learningGroup, $exam);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse($payload, 'Exam results retrieved successfully');
    }

    /**
     * GET /api/admin/learning-groups/{learningGroup}/exams/{exam}/results/export
     */
    public function exportExamResults(Request $request, LearningGroup $learningGroup, Exam $exam): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'nullable|string|in:pdf,excel',
        ]);

        try {
            $payload = $this->groupExamResultsService->getExamResultsSummary($learningGroup, $exam);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        $format = $validated['format'] ?? 'pdf';

        if ($format === 'excel') {
            return $this->exportExamResultsExcel($payload);
        }

        return $this->exportExamResultsPdf($payload);
    }

    /**
     * GET /api/admin/learning-groups/{learningGroup}/students/{student}/exam-results
     */
    public function getStudentExamResults(Request $request, LearningGroup $learningGroup, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => 'required|integer|exists:exams,id',
        ]);

        $exam = Exam::findOrFail($validated['exam_id']);

        try {
            $attempts = $this->groupExamResultsService->getStudentExamAttempts($learningGroup, $student, $exam);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(
            ExamResultResource::collection($attempts),
            'Student exam results retrieved successfully'
        );
    }

    private function exportExamResultsPdf(array $payload): JsonResponse
    {
        $pdf = Pdf::loadView('exports.exam-results-pdf', [
            'payload'     => $payload,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'exam-results-' . $payload['exam_id'] . '-' . now()->format('Ymd') . '.pdf';

        return $this->successResponse([
            'content'  => base64_encode($pdf->output()),
            'filename' => $filename,
            'mime'     => 'application/pdf',
        ], 'PDF export ready');
    }

    private function exportExamResultsExcel(array $payload): JsonResponse
    {
        $filename = 'exam-results-' . $payload['exam_id'] . '-' . now()->format('Ymd') . '.csv';

        ob_start();
        $handle = fopen('php://output', 'w');
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['#', 'Student Name', 'Email', 'Attempts', 'Highest Score', 'Status']);

        $totalMarks = $payload['total_marks'] ?? null;

        foreach ($payload['students'] as $idx => $student) {
            $hasAttempts = $student['has_attempts'] ?? false;
            $highestScore = $student['highest_score'] ?? null;

            if ($hasAttempts && $highestScore !== null) {
                $scoreDisplay = $totalMarks !== null
                    ? $highestScore . ' / ' . $totalMarks
                    : (string) $highestScore;
                $status = ($student['is_passed'] ?? false) ? 'Passed' : 'Failed';
            } else {
                $scoreDisplay = '—';
                $status = 'No attempts';
            }

            fputcsv($handle, [
                $idx + 1,
                $student['full_name'] ?? '',
                $student['email'] ?? '',
                $student['attempts_count'] ?? 0,
                $scoreDisplay,
                $status,
            ]);
        }

        fclose($handle);
        $content = ob_get_clean();

        return $this->successResponse([
            'content'  => base64_encode($content),
            'filename' => $filename,
            'mime'     => 'text/csv',
        ], 'CSV export ready');
    }
}
