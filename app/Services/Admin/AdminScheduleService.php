<?php

namespace App\Services\Admin;

use App\Models\AttendanceSession;
use App\Notifications\SessionCancelledNotification;
use App\Notifications\SessionRescheduledNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AdminScheduleService
{
    /**
     * Get all sessions across all groups with optional filters and pagination.
     *
     * Each row includes: group info, instructor, student count,
     * session number within the group, total sessions for that group,
     * and effective date/time (override takes priority over schedule values).
     */
    public function getAllSessions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // Sub-query: rank sessions within each group ordered by session_date
        $sessionRank = DB::table('attendance_sessions as s2')
            ->selectRaw('s2.id, ROW_NUMBER() OVER (PARTITION BY s2.learning_group_id ORDER BY s2.session_date ASC) as session_number')
            ->toSql();

        // Sub-query: total sessions per group
        $totalPerGroup = DB::table('attendance_sessions')
            ->selectRaw('learning_group_id, COUNT(*) as total_sessions')
            ->groupBy('learning_group_id')
            ->toSql();

        // Sub-query: enrolled students count per group
        $enrolledCount = DB::table('enrollments')
            ->selectRaw('group_id, COUNT(*) as student_count')
            ->whereNotNull('group_id')
            ->groupBy('group_id')
            ->toSql();

        $query = DB::table('attendance_sessions as sess')
            ->join('learning_groups as grp', 'sess.learning_group_id', '=', 'grp.id')
            ->join('courses as crs', 'grp.course_id', '=', 'crs.id')
            ->join('course_instructor as ci', 'grp.course_instructor_id', '=', 'ci.id')
            ->join('instructors as inst', 'ci.instructor_id', '=', 'inst.id')
            ->leftJoin('learning_group_schedules as sch', 'sess.schedule_id', '=', 'sch.id')
            ->leftJoin(DB::raw("({$sessionRank}) as ranked"), 'ranked.id', '=', 'sess.id')
            ->leftJoin(DB::raw("({$totalPerGroup}) as totals"), 'totals.learning_group_id', '=', 'sess.learning_group_id')
            ->leftJoin(DB::raw("({$enrolledCount}) as enroll"), 'enroll.group_id', '=', 'sess.learning_group_id')
            ->select([
                'sess.id',
                'sess.session_date',
                'sess.override_date',
                'sess.override_start_time',
                'sess.override_end_time',
                'sess.cancellation_reason',
                'sess.status',
                'sess.learning_group_id',
                'grp.group_name',
                'crs.title as course_title',
                'inst.id as instructor_id',
                'inst.full_name as instructor_name',
                'sch.start_time',
                'sch.end_time',
                'sch.room',
                DB::raw('COALESCE(ranked.session_number, 0) as session_number'),
                DB::raw('COALESCE(totals.total_sessions, 0) as total_sessions'),
                DB::raw('COALESCE(enroll.student_count, 0) as student_count'),
                // Effective date is override_date if set, otherwise session_date
                DB::raw('COALESCE(sess.override_date, sess.session_date) as effective_date'),
                DB::raw('COALESCE(sess.override_start_time, sch.start_time) as effective_start_time'),
                DB::raw('COALESCE(sess.override_end_time, sch.end_time) as effective_end_time'),
            ]);

        $this->applyDateFilters($query, $filters);

        // Filter by instructor
        if (!empty($filters['instructor_id'])) {
            $query->where('ci.instructor_id', $filters['instructor_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('sess.status', $filters['status']);
        }

        // Filter by group
        if (!empty($filters['group_id'])) {
            $query->where('sess.learning_group_id', $filters['group_id']);
        }

        $query->orderByRaw('COALESCE(sess.override_date, sess.session_date) ASC')
              ->orderByRaw('COALESCE(sess.override_start_time, sch.start_time) ASC');

        return $query->paginate($perPage);
    }

    /**
     * Reschedule a session: update override fields then notify students + instructor.
     */
    public function rescheduleSession(AttendanceSession $session, array $data): AttendanceSession
    {
        $oldDate      = $session->override_date ?? $session->session_date;
        $oldStartTime = $session->override_start_time ?? $session->schedule?->start_time;
        $oldEndTime   = $session->override_end_time   ?? $session->schedule?->end_time;

        $session->update([
            'override_date'       => $data['date']       ?? null,
            'override_start_time' => $data['start_time'] ?? null,
            'override_end_time'   => $data['end_time']   ?? null,
            // Reset cancelled status if it was cancelled
            'status'              => in_array($session->status, ['cancelled']) ? 'upcoming' : $session->status,
            'cancellation_reason' => null,
        ]);

        $session->load(['learningGroup.course', 'learningGroup.courseInstructor.instructor.user', 'schedule']);

        $newDate      = $session->override_date ?? $session->session_date;
        $newStartTime = $session->override_start_time ?? $session->schedule?->start_time;
        $newEndTime   = $session->override_end_time   ?? $session->schedule?->end_time;

        $notificationData = [
            'session'      => $session,
            'old_date'     => $oldDate,
            'old_start'    => $oldStartTime,
            'old_end'      => $oldEndTime,
            'new_date'     => $newDate,
            'new_start'    => $newStartTime,
            'new_end'      => $newEndTime,
        ];

        $this->notifyGroupMembers($session, 'rescheduled', $notificationData);

        return $session;
    }

    /**
     * Cancel a session: set status to cancelled then notify students + instructor.
     */
    public function cancelSession(AttendanceSession $session, ?string $reason = null): AttendanceSession
    {
        $session->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        $session->load(['learningGroup.course', 'learningGroup.courseInstructor.instructor.user', 'schedule']);

        $this->notifyGroupMembers($session, 'cancelled', ['reason' => $reason]);

        return $session;
    }

    /**
     * Notify all students enrolled in the session's group + the instructor.
     */
    private function notifyGroupMembers(AttendanceSession $session, string $type, array $data): void
    {
        $group = $session->learningGroup;

        if (!$group) {
            return;
        }

        // Gather student users
        $studentUsers = DB::table('enrollments')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('enrollments.group_id', $group->id)
            ->select('users.id')
            ->pluck('users.id');

        $group->loadMissing('courseInstructor.instructor.user');
        $instructorUserId = $group->courseInstructor?->instructor?->user_id;

        $userIds = $studentUsers->toArray();
        if ($instructorUserId) {
            $userIds[] = $instructorUserId;
        }
        $userIds = array_unique($userIds);

        if (empty($userIds)) {
            return;
        }

        $users = \App\Models\User::whereIn('id', $userIds)->get();

        if ($type === 'rescheduled') {
            Notification::send($users, new SessionRescheduledNotification($session, $data));
        } else {
            Notification::send($users, new SessionCancelledNotification($session, $data['reason'] ?? null));
        }
    }

    /**
     * Build a query result for export (no pagination).
     */
    public function getSessionsForExport(array $filters = []): \Illuminate\Support\Collection
    {
        $totalPerGroup = DB::table('attendance_sessions')
            ->selectRaw('learning_group_id, COUNT(*) as total_sessions')
            ->groupBy('learning_group_id')
            ->toSql();

        $enrolledCount = DB::table('enrollments')
            ->selectRaw('group_id, COUNT(*) as student_count')
            ->whereNotNull('group_id')
            ->groupBy('group_id')
            ->toSql();

        $sessionRank = DB::table('attendance_sessions as s2')
            ->selectRaw('s2.id, ROW_NUMBER() OVER (PARTITION BY s2.learning_group_id ORDER BY s2.session_date ASC) as session_number')
            ->toSql();

        $query = DB::table('attendance_sessions as sess')
            ->join('learning_groups as grp', 'sess.learning_group_id', '=', 'grp.id')
            ->join('courses as crs', 'grp.course_id', '=', 'crs.id')
            ->join('course_instructor as ci', 'grp.course_instructor_id', '=', 'ci.id')
            ->join('instructors as inst', 'ci.instructor_id', '=', 'inst.id')
            ->leftJoin('learning_group_schedules as sch', 'sess.schedule_id', '=', 'sch.id')
            ->leftJoin(DB::raw("({$sessionRank}) as ranked"), 'ranked.id', '=', 'sess.id')
            ->leftJoin(DB::raw("({$totalPerGroup}) as totals"), 'totals.learning_group_id', '=', 'sess.learning_group_id')
            ->leftJoin(DB::raw("({$enrolledCount}) as enroll"), 'enroll.group_id', '=', 'sess.learning_group_id')
            ->select([
                'grp.group_name',
                'crs.title as course_title',
                'inst.full_name as instructor_name',
                DB::raw('COALESCE(sess.override_date, sess.session_date) as effective_date'),
                DB::raw('COALESCE(sess.override_start_time, sch.start_time) as effective_start_time'),
                DB::raw('COALESCE(sess.override_end_time, sch.end_time) as effective_end_time'),
                'sch.room',
                DB::raw('COALESCE(enroll.student_count, 0) as student_count'),
                DB::raw('COALESCE(ranked.session_number, 0) as session_number'),
                DB::raw('COALESCE(totals.total_sessions, 0) as total_sessions'),
                'sess.status',
                'sess.cancellation_reason',
            ]);

        $this->applyDateFilters($query, $filters);

        if (!empty($filters['instructor_id'])) {
            $query->where('ci.instructor_id', $filters['instructor_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('sess.status', $filters['status']);
        }

        if (!empty($filters['group_id'])) {
            $query->where('sess.learning_group_id', $filters['group_id']);
        }

        return $query
            ->orderByRaw('COALESCE(sess.override_date, sess.session_date) ASC')
            ->orderByRaw('COALESCE(sess.override_start_time, sch.start_time) ASC')
            ->get();
    }

    /**
     * Apply single-day or week-range filter on effective session date.
     */
    private function applyDateFilters($query, array $filters): void
    {
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $from = $filters['date_from'];
            $to   = $filters['date_to'];

            $query->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('sess.override_date')
                          ->whereBetween('sess.override_date', [$from, $to]);
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->whereNull('sess.override_date')
                          ->whereBetween('sess.session_date', [$from, $to]);
                });
            });

            return;
        }

        if (!empty($filters['date'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(function ($inner) use ($filters) {
                    $inner->whereNotNull('sess.override_date')
                          ->whereDate('sess.override_date', $filters['date']);
                })->orWhere(function ($inner) use ($filters) {
                    $inner->whereNull('sess.override_date')
                          ->whereDate('sess.session_date', $filters['date']);
                });
            });
        }
    }
}
