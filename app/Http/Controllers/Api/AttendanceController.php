<?php

namespace App\Http\Controllers\Api;

use App\Events\StudentScanned;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Services\Attendance\AttendanceSessionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @tags Attendance
 */
class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceSessionService $attendanceSessionService
    ) {}

    /**
     * POST /api/attendance/scan
     * For hardware QR-scanner devices: validate the QR code and record attendance.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code'   => 'required|string',
            'device_id' => 'required|string',
        ]);

        $qrCode = $request->input('qr_code');

        // Resolve whether this is a student QR (att_*) or a session QR (sess_*)
        if (str_starts_with($qrCode, 'att_')) {
            return $this->processStudentQr($qrCode);
        }

        return $this->errorResponse('Invalid QR code format.', 400);
    }

    /**
     * GET /api/instructor/schedule/today
     * Returns the authenticated instructor's sessions for today.
     */
    public function todaySchedule(Request $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        $today = Carbon::today()->toDateString();

        $sessions = AttendanceSession::whereDate('session_date', $today)
            ->whereHas('learningGroup', function ($q) use ($instructor) {
                $q->where('instructor_id', $instructor->id);
            })
            ->with([
                'schedule',
                'learningGroup:id,group_name,course_id',
                'learningGroup.course:id,title',
            ])
            ->orderBy('session_date')
            ->get()
            ->map(function ($session) {
                return [
                    'session_id'   => $session->id,
                    'group_name'   => $session->learningGroup->group_name,
                    'course_title' => $session->learningGroup->course->title ?? null,
                    'session_date' => $session->session_date->format('Y-m-d'),
                    'start_time'   => $session->schedule->start_time->format('H:i'),
                    'end_time'     => $session->schedule->end_time->format('H:i'),
                    'room'         => $session->schedule->room,
                    'status'       => $session->status,
                    'qr_code'      => $session->qr_code,
                ];
            });

        return $this->successResponse($sessions, 'Today\'s schedule retrieved successfully');
    }

    /**
     * GET /api/instructor/attendance/today-schedule
     * Returns today's sessions with attendance stats for the authenticated instructor.
     */
    public function instructorTodaySchedule(Request $request): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        $today = Carbon::today()->toDateString();

        $sessions = AttendanceSession::whereDate('session_date', $today)
            ->whereHas('learningGroup', function ($q) use ($instructor) {
                $q->where('instructor_id', $instructor->id);
            })
            ->with([
                'schedule',
                'learningGroup:id,group_name,course_id',
                'learningGroup.course:id,title',
                'attendanceRecords',
            ])
            ->orderBy('session_date')
            ->get()
            ->map(function ($session) {
                $records     = $session->attendanceRecords;
                $totalInGroup = $session->learningGroup
                    ->students()
                    ->count();
                $presentCount = $records->whereIn('status', ['present', 'late'])->count();
                $absentCount  = $records->where('status', 'absent')->count();

                return [
                    'session_id'    => $session->id,
                    'group_name'    => $session->learningGroup->group_name,
                    'course_title'  => $session->learningGroup->course->title ?? null,
                    'session_date'  => $session->session_date->format('Y-m-d'),
                    'start_time'    => $session->schedule->start_time->format('H:i'),
                    'end_time'      => $session->schedule->end_time->format('H:i'),
                    'room'          => $session->schedule->room,
                    'status'        => $session->status,
                    'qr_code'       => $session->qr_code,
                    'attendance'    => [
                        'total'   => $totalInGroup,
                        'present' => $presentCount,
                        'absent'  => $absentCount,
                    ],
                ];
            });

        return $this->successResponse($sessions, 'Today\'s schedule retrieved successfully');
    }

    /**
     * GET /api/instructor/attendance/sessions/{session}
     * Returns session details with all enrolled students and their attendance status.
     */
    public function getSessionDetails(Request $request, AttendanceSession $session): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        // Verify the instructor owns this session's learning group
        if ($session->learningGroup->instructor_id !== $instructor->id) {
            return $this->errorResponse('Access denied. You do not own this session.', 403);
        }

        return $this->successResponse(
            $this->attendanceSessionService->getSessionDetails($session),
            'Session details retrieved successfully'
        );
    }

    /**
     * POST /api/instructor/attendance/mark
     * Manually marks attendance for a student by the instructor.
     */
    public function markAttendance(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|integer|exists:attendance_sessions,id',
            'student_id' => 'required|integer|exists:students,id',
            'status'     => 'required|in:present,absent,late',
            'notes' => ['nullable', 'string', 'max:255'],
            'student_qr_code' => 'nullable|string',
        ]);

        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        $session = AttendanceSession::findOrFail($request->session_id);

        // Verify the instructor owns this session
        if ($session->learningGroup->instructor_id !== $instructor->id) {
            return $this->errorResponse('Access denied. You do not own this session.', 403);
        }

        $record = AttendanceRecord::updateOrCreate(
            [
                'session_id' => $request->session_id,
                'student_id' => $request->student_id,
            ],
            [
                'status'    => $request->status,
                'marked_by' => 'instructor_manual',
                'marked_at' => Carbon::now(),
                'notes'     => $request->notes,
                'student_qr_code' => $request->student_qr_code ?? null,
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
     * GET /api/instructor/attendance/sessions/{session}/qr
     * Returns the QR code for an active session (for display to students).
     */
    public function getSessionQr(Request $request, AttendanceSession $session): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        // Verify the instructor owns this session
        if ($session->learningGroup->instructor_id !== $instructor->id) {
            return $this->errorResponse('Access denied. You do not own this session.', 403);
        }

        if ($session->status !== 'active') {
            return $this->errorResponse(
                "QR code is only available for active sessions. Current status: {$session->status}.",
                400
            );
        }

        $session->load('schedule');
        $endTime   = Carbon::parse($session->session_date->format('Y-m-d') . ' ' . $session->schedule->end_time->format('H:i'));
        $expiresAt = $endTime->copy()->addMinutes(30);

        return $this->successResponse([
            'qr_code'    => $session->qr_code,
            'session_id' => $session->id,
            'expires_at' => $expiresAt->toDateTimeString(),
        ], 'QR code retrieved successfully');
    }

    /**
     * GET /api/instructor/attendance/sessions/{session}/records
     * Returns attendance records created after the given `since` timestamp (ms).
     * Used for polling-based real-time updates on the instructor dashboard.
     */
    public function getSessionRecords(Request $request, AttendanceSession $session): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if (!$instructor) {
            return $this->errorResponse('Instructor profile not found.', 404);
        }

        if ($session->learningGroup->instructor_id !== $instructor->id) {
            return $this->errorResponse('Access denied. You do not own this session.', 403);
        }

        $query = AttendanceRecord::where('session_id', $session->id)
            ->with('student')
            ->orderBy('marked_at', 'desc')
            ->limit(20);

        $since = $request->query('since');
        if ($since !== null) {
            $sinceDate = Carbon::createFromTimestamp((int) $since / 1000);
            $query->where('marked_at', '>', $sinceDate);
        }

        $records = $query->get()->map(function ($record) {
            $student = $record->student;

            return [
                'record_id'    => $record->id,
                'student_id'   => $record->student_id,
                'student_name' => $student?->full_name ?? $student?->user?->name ?? 'Unknown',
                'session_id'   => $record->session_id,
                'status'       => $record->status,
                'marked_at'    => $record->marked_at?->toDateTimeString(),
                'marked_by'    => $record->marked_by,
            ];
        });

        return $this->successResponse($records, 'Records retrieved successfully');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function processStudentQr(string $qrCode): JsonResponse
    {
        $record = AttendanceRecord::where('student_qr_code', $qrCode)->first();

        if (!$record) {
            return $this->errorResponse('QR code not found.', 404);
        }

        if ($record->qr_expires_at && Carbon::now()->gt($record->qr_expires_at)) {
            return $this->errorResponse('QR code has expired.', 410);
        }

        // Verify the linked session is still active
        $session = $record->session;

        if ($session->status !== 'active') {
            return $this->errorResponse("Session is not active (status: {$session->status}).", 422);
        }

        // Mark as present
        $record->update([
            'status'    => 'present',
            'marked_by' => 'student_qr',
            'marked_at' => Carbon::now(),
        ]);

        // Broadcast real-time update to the session's instructor
        $record->load(['student', 'session.learningGroup']);
        broadcast(new StudentScanned($record))->toOthers();

        return $this->successResponse([
            'student_id' => $record->student_id,
            'session_id' => $record->session_id,
            'status'     => $record->status,
            'marked_at'  => $record->marked_at->toDateTimeString(),
        ], 'Attendance recorded successfully');
    }
}
