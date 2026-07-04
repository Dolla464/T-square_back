<?php

namespace App\Http\Controllers\Api\Receptionist;

use App\Events\StudentScanned;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Services\Attendance\AttendanceSessionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Receptionist: Attendance
 *
 * Centre-wide attendance management for receptionists.
 * Unlike the instructor controller, ownership checks are not applied —
 * a receptionist can view and manage attendance for all sessions.
 */
class ReceptionistAttendanceController extends Controller
{
    public function __construct(
        private AttendanceSessionService $attendanceSessionService
    ) {}

    /**
     * GET /api/receptionist/attendance/today-schedule
     * Returns all sessions across the centre for today with attendance stats.
     */
    public function todaySchedule(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $sessions = AttendanceSession::whereDate('session_date', $today)
            ->with([
                'schedule',
                'learningGroup:id,group_name,course_id',
                'learningGroup.course:id,title',
                'learningGroup.instructor:id,full_name',
                'attendanceRecords',
            ])
            ->orderBy('session_date')
            ->get()
            ->map(function ($session) {
                $records      = $session->attendanceRecords;
                $totalInGroup = $session->learningGroup->students()->count();
                $presentCount = $records->whereIn('status', ['present', 'late'])->count();
                $absentCount  = $records->where('status', 'absent')->count();

                return [
                    'session_id'      => $session->id,
                    'group_name'      => $session->learningGroup->group_name,
                    'course_title'    => $session->learningGroup->course->title ?? null,
                    'instructor_name' => $session->learningGroup->instructor->full_name ?? null,
                    'session_date'    => $session->session_date->format('Y-m-d'),
                    'start_time'      => $session->schedule->start_time->format('H:i'),
                    'end_time'        => $session->schedule->end_time->format('H:i'),
                    'room'            => $session->schedule->room,
                    'status'          => $session->status,
                    'qr_code'         => $session->qr_code,
                    'attendance'      => [
                        'total'   => $totalInGroup,
                        'present' => $presentCount,
                        'absent'  => $absentCount,
                    ],
                ];
            });

        return $this->successResponse($sessions, "Today's schedule retrieved successfully");
    }

    /**
     * GET /api/receptionist/attendance/sessions/{session}
     * Returns session details with all enrolled students and their attendance status.
     * No ownership check — receptionists can access any session.
     */
    public function getSessionDetails(Request $request, AttendanceSession $session): JsonResponse
    {
        return $this->successResponse(
            $this->attendanceSessionService->getSessionDetails($session),
            'Session details retrieved successfully'
        );
    }

    /**
     * GET /api/receptionist/attendance/sessions/{session}/qr
     * Returns the QR code for an active session.
     */
    public function getSessionQr(Request $request, AttendanceSession $session): JsonResponse
    {
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
     * GET /api/receptionist/attendance/sessions/{session}/records
     * Returns recent attendance records for polling-based real-time updates.
     */
    public function getSessionRecords(Request $request, AttendanceSession $session): JsonResponse
    {
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

    /**
     * POST /api/receptionist/attendance/mark
     * Manually marks attendance for a student.
     * marked_by is set to 'receptionist_manual' to distinguish from instructor marks.
     */
    public function markAttendance(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|integer|exists:attendance_sessions,id',
            'student_id' => 'required|integer|exists:students,id',
            'status'     => 'required|in:present,absent,late',
            'notes'      => ['nullable', 'string', 'max:255'],
        ]);

        $session = AttendanceSession::findOrFail($request->session_id);

        $record = AttendanceRecord::updateOrCreate(
            [
                'session_id' => $request->session_id,
                'student_id' => $request->student_id,
            ],
            [
                'status'    => $request->status,
                'marked_by' => 'receptionist_manual',
                'marked_at' => Carbon::now(),
                'notes'     => $request->notes,
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
}
