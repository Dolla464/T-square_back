<?php

namespace App\Services\Instructor;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\LearningGroup;
use Carbon\Carbon;

class InstructorDashboardService
{
    // ── Overview Stats ────────────────────────────────────────────────────────

    public function getStats(int $instructorId): array
    {
        $groupIds = LearningGroup::where('instructor_id', $instructorId)->pluck('id');

        $totalGroups     = $groupIds->count();
        $activeGroups    = LearningGroup::where('instructor_id', $instructorId)->where('status', 'active')->count();
        $completedGroups = LearningGroup::where('instructor_id', $instructorId)->where('status', 'completed')->count();

        $totalStudents = Enrollment::whereIn('group_id', $groupIds)
            ->selectRaw('COUNT(DISTINCT student_id) as count')
            ->first()
            ->count;

        return [
            'total_groups'     => $totalGroups,
            'active_groups'    => $activeGroups,
            'completed_groups' => $completedGroups,
            'total_students'   => $totalStudents,
        ];
    }

    // ── Active Groups Table ───────────────────────────────────────────────────

    public function getActiveGroups(int $instructorId): array
    {
        $groups = LearningGroup::where('instructor_id', $instructorId)
            ->where('status', 'active')
            ->with(['course:id,title'])
            ->withCount([
                'attendanceSessions as total_sessions',
                'attendanceSessions as completed_sessions' => function ($q) {
                    $q->where('status', 'completed');
                },
            ])
            ->get();

        return $groups->map(function (LearningGroup $group) {
            $totalSessions     = $group->total_sessions;
            $completedSessions = $group->completed_sessions;
            $studentsCount     = Enrollment::where('group_id', $group->id)
                ->distinct('student_id')
                ->count('student_id');

            $completionPercentage = $totalSessions > 0
                ? round(($completedSessions / $totalSessions) * 100, 1)
                : 0;

            return [
                'id'                    => $group->id,
                'group_name'            => $group->group_name,
                'course_title'          => $group->course->title ?? null,
                'students_count'        => $studentsCount,
                'completion_percentage' => $completionPercentage,
                'completed_sessions'    => $completedSessions,
                'total_sessions'        => $totalSessions,
                'start_date'            => $group->start_date?->format('Y-m-d'),
                'end_date'              => $group->end_date?->format('Y-m-d'),
            ];
        })->values()->all();
    }

    // ── Completed Groups Table ────────────────────────────────────────────────

    public function getCompletedGroups(int $instructorId, int $page = 1, int $perPage = 10): array
    {
        $paginator = LearningGroup::where('instructor_id', $instructorId)
            ->where('status', 'completed')
            ->with(['course:id,title'])
            ->withCount('attendanceSessions as total_sessions')
            // Nulls last: groups with end_date come before those without
            ->orderByRaw('CASE WHEN end_date IS NULL THEN 1 ELSE 0 END, end_date DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->through(function (LearningGroup $group) {
            $studentsCount = Enrollment::where('group_id', $group->id)
                ->distinct('student_id')
                ->count('student_id');

            // Prefer end_date; fall back to latest session_date
            $completionDate = $group->end_date?->format('Y-m-d');
            if (!$completionDate) {
                $lastSession = AttendanceSession::where('group_id', $group->id)
                    ->orderByDesc('session_date')
                    ->value('session_date');
                $completionDate = $lastSession
                    ? Carbon::parse($lastSession)->format('Y-m-d')
                    : null;
            }

            return [
                'id'              => $group->id,
                'group_name'      => $group->group_name,
                'course_title'    => $group->course->title ?? null,
                'students_count'  => $studentsCount,
                'completion_date' => $completionDate,
                'total_sessions'  => $group->total_sessions,
                'start_date'      => $group->start_date?->format('Y-m-d'),
            ];
        });

        return [
            'data' => array_values($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    // ── Group Details ─────────────────────────────────────────────────────────

    public function getGroupDetails(LearningGroup $group): array
    {
        $group->load(['course:id,title', 'attendanceSessions']);

        $totalSessions     = $group->attendanceSessions->count();
        $completedSessions = $group->attendanceSessions->where('status', 'completed')->count();
        $sessionIds        = $group->attendanceSessions->pluck('id');

        $completionPercentage = $totalSessions > 0
            ? round(($completedSessions / $totalSessions) * 100, 1)
            : 0;

        $students     = $group->students()->with('user:id,email')->get();
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
                'student_id'           => $student->id,
                'full_name'            => $student->full_name ?? $student->user?->name ?? 'Unknown',
                'email'                => $student->user?->email ?? null,
                'avatar'               => $student->avatar ?? null,
                'attended_sessions'    => $attendedSessions,
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
                'percentage'          => $completionPercentage,
                'completed_sessions'  => $completedSessions,
                'total_sessions'      => $totalSessions,
            ],
            'students' => $studentsData->values()->all(),
        ];
    }

    // ── Schedule ──────────────────────────────────────────────────────────────

    public function getSchedule(int $instructorId, ?string $date = null): array
    {
        $query = AttendanceSession::whereHas('learningGroup', function ($q) use ($instructorId) {
            $q->where('instructor_id', $instructorId);
        })->with([
            'schedule',
            'learningGroup:id,group_name,course_id',
            'learningGroup.course:id,title',
        ]);

        if ($date) {
            $targetDate = Carbon::parse($date)->toDateString();
            $sessions   = $query->whereDate('session_date', $targetDate)
                ->orderBy('session_date')
                ->get()
                ->map(fn($s) => $this->formatSession($s));

            return [
                'type'     => 'day',
                'date'     => $targetDate,
                'sessions' => $sessions->values()->all(),
            ];
        }

        // Default: current week (Monday → Sunday)
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek   = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $sessions = $query->whereBetween('session_date', [$startOfWeek, $endOfWeek])
            ->orderBy('session_date')
            ->get()
            ->map(fn($s) => $this->formatSession($s));

        return [
            'type'       => 'week',
            'start_date' => $startOfWeek,
            'end_date'   => $endOfWeek,
            'sessions'   => $sessions->values()->all(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatSession(AttendanceSession $session): array
    {
        return [
            'session_id'   => $session->id,
            'group_name'   => $session->learningGroup->group_name,
            'course_title' => $session->learningGroup->course->title ?? null,
            'session_date' => $session->session_date->format('Y-m-d'),
            'start_time'   => $session->schedule->start_time->format('H:i'),
            'end_time'     => $session->schedule->end_time->format('H:i'),
            'room'         => $session->schedule->room,
            'status'       => $session->status,
        ];
    }
}
