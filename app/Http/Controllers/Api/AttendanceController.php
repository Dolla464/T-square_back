<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
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
     * GET /api/student/attendance/qr
     * Student requests their attendance QR for the currently active session.
     */
    public function getStudentQr(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        if (!$student) {
            return $this->errorResponse('Student profile not found.', 404);
        }

        $now = Carbon::now();

        // Find an active session the student is enrolled in
        $activeSession = AttendanceSession::where('status', 'active')
            ->whereDate('session_date', $now->toDateString())
            ->whereHas('learningGroup', function ($q) use ($student) {
                $q->whereHas('students', function ($sq) use ($student) {
                    $sq->where('students.id', $student->id);
                });
            })
            ->with('schedule')
            ->first();

        if (!$activeSession) {
            return $this->errorResponse('No active session found for your enrolled groups.', 404);
        }

        // Validate within allowed window: start_time - 30 min to end_time + 30 min
        $schedule  = $activeSession->schedule;
        $startTime = Carbon::parse($activeSession->session_date->format('Y-m-d') . ' ' . $schedule->start_time->format('H:i'));
        $endTime   = Carbon::parse($activeSession->session_date->format('Y-m-d') . ' ' . $schedule->end_time->format('H:i'));

        $windowStart = $startTime->copy()->subMinutes(30);
        $windowEnd   = $endTime->copy()->addMinutes(30);

        if ($now->lt($windowStart) || $now->gt($windowEnd)) {
            return $this->errorResponse(
                "QR code is only available between {$windowStart->format('H:i')} and {$windowEnd->format('H:i')}.",
                403
            );
        }

        // Generate or refresh the student's QR for this session
        $expiresAt = $endTime->copy()->addMinutes(30);
        $qrCode    = 'att_' . Str::random(16);

        $record = AttendanceRecord::updateOrCreate(
            ['session_id' => $activeSession->id, 'student_id' => $student->id],
            [
                'student_qr_code' => $qrCode,
                'status'          => 'absent',    // will be updated when scanned
                'marked_by'       => 'student_qr',
                'qr_expires_at'   => $expiresAt,
            ]
        );

        return $this->successResponse([
            'qr_code'    => $record->student_qr_code,
            'session_id' => $activeSession->id,
            'expires_at' => $expiresAt->toDateTimeString(),
        ], 'QR code generated successfully');
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

        $session->load([
            'schedule',
            'learningGroup:id,group_name,course_id,instructor_id',
            'learningGroup.course:id,title',
            'attendanceRecords',
        ]);

        // Get all students enrolled in the group
        $students = $session->learningGroup->students()->get();

        // Map students with their attendance record
        $studentList = $students->map(function ($student) use ($session) {
            $record = $session->attendanceRecords
                ->where('student_id', $student->id)
                ->first();

            return [
                'student_id' => $student->id,
                'full_name'  => $student->full_name ?? $student->user?->name ?? 'Unknown',
                'email'      => $student->email ?? $student->user?->email ?? null,
                'avatar'     => $student->avatar ?? $student->user?->avatar ?? null,
                'status'     => $record?->status ?? 'not_marked',
                'marked_at'  => $record?->marked_at?->toDateTimeString(),
                'marked_by'  => $record?->marked_by,
                'notes'      => $record?->notes,
            ];
        });

        $presentCount = $studentList->whereIn('status', ['present', 'late'])->count();

        return $this->successResponse([
            'session_id'   => $session->id,
            'group_name'   => $session->learningGroup->group_name,
            'course_title' => $session->learningGroup->course->title ?? null,
            'session_date' => $session->session_date->format('Y-m-d'),
            'start_time'   => $session->schedule->start_time->format('H:i'),
            'end_time'     => $session->schedule->end_time->format('H:i'),
            'room'         => $session->schedule->room,
            'status'       => $session->status,
            'qr_code'      => $session->qr_code,
            'attendance'   => [
                'total'   => $studentList->count(),
                'present' => $presentCount,
                'absent'  => $studentList->count() - $presentCount,
            ],
            'students'     => $studentList->values(),
        ], 'Session details retrieved successfully');
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

        return $this->successResponse([
            'student_id' => $record->student_id,
            'session_id' => $record->session_id,
            'status'     => $record->status,
            'marked_at'  => $record->marked_at->toDateTimeString(),
        ], 'Attendance recorded successfully');
    }
}
