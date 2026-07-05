<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\LearningGroup;
use App\Models\Student;

class GroupAttendanceSummaryService
{
    public function __construct(
        private AttendanceSessionService $attendanceSessionService
    ) {}

    public function getGroupDetails(LearningGroup $group): array
    {
        $group->load(['course:id,title', 'attendanceSessions']);

        $totalSessions     = $group->attendanceSessions->count();
        $completedSessions = $group->attendanceSessions->where('status', 'completed')->count();
        $sessionIds        = $group->attendanceSessions->pluck('id');

        $completionPercentage = $totalSessions > 0
            ? round(($completedSessions / $totalSessions) * 100, 1)
            : 0;

        $students = $group->students()->with('user:id,email')->get();
        $attendanceCounts = AttendanceRecord::whereIn('session_id', $sessionIds)
            ->whereIn('status', ['present', 'late'])
            ->selectRaw('student_id, COUNT(*) as count')
            ->groupBy('student_id')
            ->pluck('count', 'student_id');

        $studentsData = $students->map(function ($student) use ($attendanceCounts, $totalSessions) {
            $attendedSessions = $attendanceCounts[$student->id] ?? 0;

            $attendancePercentage = $totalSessions > 0
                ? round(($attendedSessions / $totalSessions) * 100, 1)
                : 0;

            return [
                'student_id'            => $student->id,
                'full_name'             => $student->full_name ?? $student->user?->name ?? 'Unknown',
                'email'                 => $student->user?->email ?? null,
                'avatar'                => $student->avatar ?? null,
                'attended_sessions'     => $attendedSessions,
                'attendance_percentage' => $attendancePercentage,
            ];
        });

        return [
            'id'             => $group->id,
            'group_name'     => $group->group_name,
            'course_title'   => $group->course->title ?? null,
            'start_date'     => $group->start_date?->format('Y-m-d'),
            'end_date'       => $group->end_date?->format('Y-m-d'),
            'status'         => $group->status,
            'students_count' => $students->count(),
            'completion'     => [
                'percentage'         => $completionPercentage,
                'completed_sessions' => $completedSessions,
                'total_sessions'     => $totalSessions,
            ],
            'students' => $studentsData->values()->all(),
        ];
    }

    public function getStudentCourseAttendance(LearningGroup $group, Student $student): array
    {
        $summary = $this->getGroupDetails($group);

        $studentData = collect($summary['students'])
            ->firstWhere('student_id', $student->id);

        if (!$studentData) {
            throw new \InvalidArgumentException('Student is not enrolled in this group.');
        }

        $sessions = $group->attendanceSessions()
            ->with('schedule')
            ->orderBy('session_date')
            ->get();

        $records = AttendanceRecord::whereIn('session_id', $sessions->pluck('id'))
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('session_id');

        $sessionRows = $sessions->map(function ($session) use ($records) {
            $record = $records->get($session->id);
            $times  = $this->attendanceSessionService->getEffectiveTimes($session);

            return [
                'session_id'   => $session->id,
                'session_date' => $times['session_date'],
                'start_time'   => $times['start_time'],
                'end_time'     => $times['end_time'],
                'session_status' => $session->status,
                'status'       => $record?->status ?? 'not_marked',
            ];
        });

        return [
            'student_id'            => $student->id,
            'full_name'             => $studentData['full_name'],
            'email'                 => $studentData['email'],
            'group_name'            => $group->group_name,
            'course_title'          => $summary['course_title'],
            'attended_sessions'     => $studentData['attended_sessions'],
            'attendance_percentage' => $studentData['attendance_percentage'],
            'total_sessions'        => $summary['completion']['total_sessions'],
            'sessions'              => $sessionRows->values()->all(),
        ];
    }
}
