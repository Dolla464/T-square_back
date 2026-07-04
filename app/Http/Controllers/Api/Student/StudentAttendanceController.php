<?php

namespace App\Http\Controllers\Api\Student;

use App\Events\StudentScanned;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\LearningGroup;
use App\Models\Student;
use App\Services\Attendance\AttendanceSessionService;
use App\Services\Attendance\GroupAttendanceSummaryService;
use App\Services\Attendance\StudentAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @tags Student Attendance
 */
class StudentAttendanceController extends Controller
{
    public function __construct(
        private StudentAttendanceService $studentAttendanceService,
        private GroupAttendanceSummaryService $groupAttendanceSummaryService,
        private AttendanceSessionService $attendanceSessionService
    ) {}

    /**
     * GET /api/student/attendance/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return $this->successResponse(
            $this->studentAttendanceService->getSummary($student),
            'Attendance summary retrieved successfully'
        );
    }

    /**
     * GET /api/student/attendance/today
     */
    public function today(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return $this->successResponse(
            $this->studentAttendanceService->getTodaySessions($student),
            'Today\'s sessions retrieved successfully'
        );
    }

    /**
     * GET /api/student/attendance/schedule
     */
    public function schedule(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $student = $this->resolveStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        return $this->successResponse(
            $this->studentAttendanceService->getSchedule(
                $student,
                $request->query('from'),
                $request->query('to')
            ),
            'Schedule retrieved successfully'
        );
    }

    /**
     * GET /api/student/attendance/groups/{learningGroup}
     */
    public function groupHistory(Request $request, LearningGroup $learningGroup): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        try {
            $this->studentAttendanceService->assertStudentInGroup($student, $learningGroup);
            $payload = $this->groupAttendanceSummaryService->getStudentCourseAttendance($learningGroup, $student);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(
            $payload,
            'Group attendance history retrieved successfully'
        );
    }

    /**
     * GET /api/student/attendance/qr
     */
    public function qr(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'nullable|integer|exists:attendance_sessions,id',
        ]);

        $student = $this->resolveStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $sessionId = $request->query('session_id') ? (int) $request->query('session_id') : null;
        $session   = $this->studentAttendanceService->findActiveSessionForStudent($student, $sessionId);

        if (!$session) {
            return $this->errorResponse('No active session found for your enrolled groups.', 404);
        }

        $session->loadMissing(['learningGroup.course:id,title']);

        $now   = Carbon::now();
        $range = $this->attendanceSessionService->getEffectiveDateTimeRange($session);

        $windowStart = $range['start']->copy()->subMinutes(30);
        $windowEnd   = $range['end']->copy()->addMinutes(30);

        if ($now->lt($windowStart) || $now->gt($windowEnd)) {
            return $this->errorResponse(
                "QR code is only available between {$windowStart->format('H:i')} and {$windowEnd->format('H:i')}.",
                403
            );
        }

        $expiresAt = $range['end']->copy()->addMinutes(30);
        $qrCode    = 'att_' . Str::random(16);

        $record = AttendanceRecord::updateOrCreate(
            ['session_id' => $session->id, 'student_id' => $student->id],
            [
                'student_qr_code' => $qrCode,
                'status'          => 'absent',
                'marked_by'       => 'student_qr',
                'qr_expires_at'   => $expiresAt,
            ]
        );

        return $this->successResponse([
            'qr_code'      => $record->student_qr_code,
            'session_id'   => $session->id,
            'expires_at'   => $expiresAt->toDateTimeString(),
            'group_name'   => $session->learningGroup->group_name ?? null,
            'course_title' => $session->learningGroup->course->title ?? null,
        ], 'QR code generated successfully');
    }

    /**
     * POST /api/student/attendance/check-in
     * Student scans the session QR code (sess_*) displayed by the instructor to register their own attendance.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
        ]);

        $student = $this->resolveStudent($request);
        if ($student instanceof JsonResponse) {
            return $student;
        }

        $qrCode = $request->input('qr_code');

        if (! str_starts_with($qrCode, 'sess_')) {
            return $this->errorResponse('Invalid QR code format.', 400);
        }

        $session = AttendanceSession::where('qr_code', $qrCode)
            ->with(['schedule', 'learningGroup.course:id,title'])
            ->first();

        if (! $session) {
            return $this->errorResponse('Invalid or expired QR code.', 404);
        }

        if ($session->status !== 'active') {
            return $this->errorResponse("Session is not active (status: {$session->status}).", 422);
        }

        $enrolled = Enrollment::where('student_id', $student->id)
            ->where('group_id', $session->learning_group_id)
            ->exists();

        if (! $enrolled) {
            return $this->errorResponse('You are not enrolled in this session\'s group.', 403);
        }

        $now   = Carbon::now();
        $range = $this->attendanceSessionService->getEffectiveDateTimeRange($session);

        $windowStart = $range['start']->copy()->subMinutes(30);
        $windowEnd   = $range['end']->copy()->addMinutes(30);

        if ($now->lt($windowStart) || $now->gt($windowEnd)) {
            return $this->errorResponse(
                "Check-in is only available between {$windowStart->format('H:i')} and {$windowEnd->format('H:i')}.",
                403
            );
        }

        $record = AttendanceRecord::updateOrCreate(
            ['session_id' => $session->id, 'student_id' => $student->id],
            [
                'status'    => 'present',
                'marked_by' => 'student_app',
                'marked_at' => $now,
            ]
        );

        $record->load(['student', 'session.learningGroup']);
        broadcast(new StudentScanned($record))->toOthers();

        return $this->successResponse([
            'session_id'   => $session->id,
            'student_id'   => $student->id,
            'status'       => $record->status,
            'marked_at'    => $record->marked_at->toDateTimeString(),
            'group_name'   => $session->learningGroup->group_name ?? null,
            'course_title' => $session->learningGroup->course->title ?? null,
        ], 'Attendance recorded successfully');
    }

    private function resolveStudent(Request $request): Student|JsonResponse
    {
        $student = $request->user()?->student;

        if (!$student) {
            return $this->errorResponse('Student profile not found.', 404);
        }

        return $student;
    }
}
