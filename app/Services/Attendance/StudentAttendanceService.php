<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\LearningGroup;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StudentAttendanceService
{
    public function __construct(
        private AttendanceSessionService $sessionService,
        private GroupAttendanceSummaryService $summaryService
    ) {}

    public function getEnrolledGroupIds(Student $student): Collection
    {
        return Enrollment::where('student_id', $student->id)
            ->whereNotNull('group_id')
            ->pluck('group_id');
    }

    public function assertStudentInGroup(Student $student, LearningGroup $group): void
    {
        $enrolled = Enrollment::where('student_id', $student->id)
            ->where('group_id', $group->id)
            ->exists();

        if (!$enrolled) {
            throw new \InvalidArgumentException('You are not enrolled in this group.');
        }
    }

    public function getSummary(Student $student): array
    {
        $groupIds = $this->getEnrolledGroupIds($student);

        if ($groupIds->isEmpty()) {
            return [];
        }

        $groups = LearningGroup::whereIn('id', $groupIds)
            ->with('course:id,title')
            ->get();

        return $groups->map(function (LearningGroup $group) use ($student) {
            try {
                $data = $this->summaryService->getStudentCourseAttendance($group, $student);

                return [
                    'group_id'              => $group->id,
                    'group_name'            => $group->group_name,
                    'course_id'             => $group->course_id,
                    'course_title'          => $data['course_title'],
                    'attendance_percentage' => $data['attendance_percentage'],
                    'attended_sessions'     => $data['attended_sessions'],
                    'total_sessions'        => $data['total_sessions'],
                ];
            } catch (\InvalidArgumentException) {
                return null;
            }
        })->filter()->values()->all();
    }

    public function getTodaySessions(Student $student): array
    {
        $groupIds = $this->getEnrolledGroupIds($student);

        if ($groupIds->isEmpty()) {
            return [];
        }

        $today = Carbon::today()->toDateString();
        $now   = Carbon::now();

        $sessions = AttendanceSession::whereIn('learning_group_id', $groupIds)
            ->where(function ($q) use ($today) {
                $q->whereDate('session_date', $today)
                    ->orWhereDate('override_date', $today);
            })
            ->with(['schedule', 'learningGroup.course:id,title'])
            ->get()
            ->filter(function (AttendanceSession $session) use ($today) {
                $range = $this->sessionService->getEffectiveDateTimeRange($session);

                return $range['session_date'] === $today;
            })
            ->sortBy(fn (AttendanceSession $session) => $this->sessionService->getEffectiveDateTimeRange($session)['start']);

        $records = AttendanceRecord::whereIn('session_id', $sessions->pluck('id'))
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('session_id');

        return $sessions->values()->map(function (AttendanceSession $session) use ($now, $records) {
            return $this->formatStudentSessionRow($session, $now, $records->get($session->id));
        })->all();
    }

    public function getSchedule(Student $student, ?string $from = null, ?string $to = null): array
    {
        $groupIds = $this->getEnrolledGroupIds($student);

        if ($groupIds->isEmpty()) {
            return [];
        }

        $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::today();
        $toDate   = $to ? Carbon::parse($to)->endOfDay() : $fromDate->copy()->addDays(30)->endOfDay();

        $sessions = AttendanceSession::whereIn('learning_group_id', $groupIds)
            ->where('status', '!=', 'cancelled')
            ->with(['schedule', 'learningGroup.course:id,title'])
            ->get()
            ->filter(function (AttendanceSession $session) use ($fromDate, $toDate) {
                $range = $this->sessionService->getEffectiveDateTimeRange($session);
                $date  = Carbon::parse($range['session_date']);

                return $date->between($fromDate, $toDate);
            })
            ->sortBy(fn (AttendanceSession $session) => $this->sessionService->getEffectiveDateTimeRange($session)['start']);

        $records = AttendanceRecord::whereIn('session_id', $sessions->pluck('id'))
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('session_id');

        return $sessions->values()->map(function (AttendanceSession $session) use ($records) {
            $times  = $this->sessionService->getEffectiveTimes($session);
            $record = $records->get($session->id);

            return [
                'session_id'     => $session->id,
                'group_id'       => $session->learning_group_id,
                'group_name'     => $session->learningGroup->group_name,
                'course_title'   => $session->learningGroup->course->title ?? null,
                'session_date'   => $times['session_date'],
                'start_time'     => $times['start_time'],
                'end_time'       => $times['end_time'],
                'room'           => $session->schedule->room ?? null,
                'status'         => $session->status,
                'student_status' => $this->resolveStudentStatus($record, $session),
            ];
        })->all();
    }

    public function findActiveSessionForStudent(Student $student, ?int $sessionId = null): ?AttendanceSession
    {
        $groupIds = $this->getEnrolledGroupIds($student);

        if ($groupIds->isEmpty()) {
            return null;
        }

        $today = Carbon::today()->toDateString();

        $query = AttendanceSession::where('status', 'active')
            ->whereIn('learning_group_id', $groupIds)
            ->where(function ($q) use ($today) {
                $q->whereDate('session_date', $today)
                    ->orWhereDate('override_date', $today);
            })
            ->with(['schedule', 'learningGroup.course:id,title']);

        if ($sessionId) {
            $query->where('id', $sessionId);
        }

        return $query->get()
            ->filter(function (AttendanceSession $session) use ($today) {
                $range = $this->sessionService->getEffectiveDateTimeRange($session);

                return $range['session_date'] === $today;
            })
            ->sortBy(fn (AttendanceSession $session) => $this->sessionService->getEffectiveDateTimeRange($session)['start'])
            ->first();
    }

    private function formatStudentSessionRow(
        AttendanceSession $session,
        Carbon $now,
        ?AttendanceRecord $record
    ): array {
        $times = $this->sessionService->getEffectiveTimes($session);
        $range = $this->sessionService->getEffectiveDateTimeRange($session);

        $windowStart = $range['start']->copy()->subMinutes(30);
        $windowEnd   = $range['end']->copy()->addMinutes(30);
        $qrAvailable = $session->status === 'active' && $now->between($windowStart, $windowEnd);

        return [
            'session_id'     => $session->id,
            'group_id'       => $session->learning_group_id,
            'group_name'     => $session->learningGroup->group_name,
            'course_title'   => $session->learningGroup->course->title ?? null,
            'session_date'   => $times['session_date'],
            'start_time'     => $times['start_time'],
            'end_time'       => $times['end_time'],
            'room'           => $session->schedule->room ?? null,
            'status'         => $session->status,
            'student_status' => $this->resolveStudentStatus($record, $session),
            'marked_at'      => $record?->marked_at?->toDateTimeString(),
            'qr_available'   => $qrAvailable,
            'qr_window'      => [
                'start' => $windowStart->format('H:i'),
                'end'   => $windowEnd->format('H:i'),
            ],
        ];
    }

    private function resolveStudentStatus(?AttendanceRecord $record, AttendanceSession $session): string
    {
        if (!$record) {
            return $session->status === 'completed' ? 'absent' : 'not_marked';
        }

        if (in_array($record->status, ['present', 'late'], true)) {
            return $record->status;
        }

        if ($record->status === 'absent') {
            return 'absent';
        }

        return 'not_marked';
    }
}
