<?php

namespace App\Services\Admin;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\LearningGroup;
use App\Http\Resources\Admin\LearningGroup\AdminLearningGroupResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminLearningGroupService
{
    /**
     * Get all groups paginated with dynamic search filter
     */
    public function getAllGroups($perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        $query = LearningGroup::with(['course:id,title', 'instructor:id,full_name', 'schedules'])
            ->withCount('students');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('group_name', 'LIKE', "%{$search}%")
                    ->orWhereHas('course', function ($courseQuery) use ($search) {
                        $courseQuery->where('title', 'LIKE', "%{$search}%");
                    });
            });
        }

        $groups = $query->latest()->paginate($perPage);

        $groups->getCollection()->transform(function ($group) {
            return new AdminLearningGroupResource($group);
        });

        return $groups;
    }

    /**
     * Get the selection data for the frontend
     */
    public function getSelection()
    {
        return LearningGroup::select('id', 'group_name')->get()->map(function ($group) {
            return [
                'id'   => $group->id,
                'name' => $group->group_name,
            ];
        });
    }

    /**
     * Create a new group with schedules and auto-generate attendance sessions
     */
    public function createGroup(array $data): AdminLearningGroupResource
    {
        return DB::transaction(function () use ($data) {
            $course    = Course::findOrFail($data['course_id']);
            $startDate = Carbon::parse($data['start_date']);
            $endDate   = $startDate->copy()->addWeeks($course->duration_weeks);

            $group = LearningGroup::create([
                'group_name'    => $data['group_name'],
                'course_id'     => $data['course_id'],
                'instructor_id' => $data['instructor_id'],
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'status'        => $data['status'] ?? 'active',
            ]);

            if (!empty($data['schedules'])) {
                foreach ($data['schedules'] as $schedule) {
                    $group->schedules()->create($schedule);
                }
            }

            $this->generateAttendanceSessions($group);

            $group->load(['course:id,title', 'instructor:id,full_name', 'schedules']);

            return new AdminLearningGroupResource($group);
        });
    }

    /**
     * Get single group details with its assigned students, relationships, and counts
     */
    public function getGroupDetails(LearningGroup $group): AdminLearningGroupResource
    {
        $group->load(['course:id,title', 'instructor:id,full_name', 'schedules']);
        $group->loadCount('students');

        $students = DB::table('enrollments')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('enrollments.group_id', $group->id)
            ->select(
                'students.id',
                'students.full_name',
                'users.email',
                'students.phone',
                'enrollments.is_completed',
                'enrollments.completed_at',
                'enrollments.updated_at as assigned_at'
            )
            ->latest('enrollments.updated_at')
            ->get();

        $group->setRelation('assigned_students', $students);

        return new AdminLearningGroupResource($group);
    }

    /**
     * Update group details, sync schedules, and regenerate attendance sessions
     */
    public function updateGroup(LearningGroup $group, array $data): AdminLearningGroupResource
    {
        return DB::transaction(function () use ($group, $data) {
            $updateData = [
                'group_name'    => $data['group_name'],
                'course_id'     => $data['course_id'],
                'instructor_id' => $data['instructor_id'],
            ];

            if (isset($data['start_date'])) {
                $course                  = Course::findOrFail($data['course_id']);
                $startDate               = Carbon::parse($data['start_date']);
                $updateData['start_date'] = $startDate;
                $updateData['end_date']   = $startDate->copy()->addWeeks($course->duration_weeks);
            }

            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            $group->update($updateData);

            if (isset($data['schedules'])) {
                // Deleting schedules cascades to attendance_sessions via DB constraint
                $group->schedules()->delete();

                foreach ($data['schedules'] as $schedule) {
                    $group->schedules()->create($schedule);
                }

                $group->attendanceSessions()->delete();
                $this->generateAttendanceSessions($group);
            }

            // ── Student sync (existing logic) ─────────────────────────────────
            $incomingStudentIds = isset($data['student_ids'])
                ? array_map('intval', array_filter((array) $data['student_ids']))
                : [];

            $studentStatuses = $data['student_statuses'] ?? [];

            DB::table('enrollments')
                ->where('group_id', $group->id)
                ->whereNotIn('student_id', $incomingStudentIds)
                ->update(['group_id' => null, 'updated_at' => now()]);

            foreach ($incomingStudentIds as $studentId) {
                $isCompleted = (bool) ($studentStatuses[$studentId]
                    ?? $studentStatuses[(string) $studentId]
                    ?? false);

                DB::table('enrollments')->updateOrInsert(
                    ['student_id' => $studentId, 'course_id' => $group->course_id],
                    [
                        'group_id'     => $group->id,
                        'is_completed' => $isCompleted,
                        'completed_at' => $isCompleted ? now() : null,
                        'updated_at'   => now(),
                    ]
                );
            }

            $actualCount = DB::table('enrollments')->where('group_id', $group->id)->count();
            DB::table('learning_groups')
                ->where('id', $group->id)
                ->update(['enrolled_students' => $actualCount]);

            $group->refresh();
            $group->load(['course:id,title', 'instructor:id,full_name', 'schedules', 'attendanceSessions']);

            return new AdminLearningGroupResource($group);
        });
    }

    /**
     * Delete the group
     */
    public function deleteGroup(LearningGroup $group): ?bool
    {
        return $group->delete();
    }

    /**
     * Generate attendance sessions for every schedule day between start and end date
     */
    public function generateAttendanceSessions(LearningGroup $group): void
    {
        $group->refresh();
        $group->load('schedules');

        $schedules   = $group->schedules;
        $currentDate = $group->start_date->copy();
        $endDate     = $group->end_date->copy();

        // Map our custom day numbering (0=Sat … 6=Fri) to Carbon's dayOfWeek (0=Sun … 6=Sat)
        // Our: 0=Sat,1=Sun,2=Mon,3=Tue,4=Wed,5=Thu,6=Fri
        // Carbon: 0=Sun,1=Mon,2=Tue,3=Wed,4=Thu,5=Fri,6=Sat
        $dayMap = [0 => 6, 1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5];

        while ($currentDate->lte($endDate)) {
            foreach ($schedules as $schedule) {
                $carbonDay = $dayMap[$schedule->day_of_week] ?? null;

                if ($carbonDay !== null && $currentDate->dayOfWeek === $carbonDay) {
                    AttendanceSession::create([
                        'learning_group_id' => $group->id,
                        'schedule_id'       => $schedule->id,
                        'session_date'      => $currentDate->copy(),
                        'status'            => 'upcoming',
                    ]);
                }
            }
            $currentDate->addDay();
        }
    }

    /**
     * Get the unassigned students for a specific course
     */
    public function getUnassignedCourseStudents(int $groupId): array
    {
        $group = DB::table('learning_groups')->where('id', $groupId)->first();

        if (!$group) {
            return ['success' => false, 'status' => 404, 'message' => 'Group not found.'];
        }

        $students = DB::table('enrollments')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('orders', 'enrollments.order_id', '=', 'orders.id')
            ->where('enrollments.course_id', $group->course_id)
            ->whereNull('enrollments.group_id')
            ->where(function ($query) {
                $query->where('orders.status', 'completed')
                    ->orWhereNull('enrollments.order_id');
            })
            ->select(
                'students.id',
                'students.full_name',
                'students.phone',
                'users.email',
                'enrollments.created_at as enrolled_at'
            )
            ->get()
            ->toArray();

        return ['success' => true, 'status' => 200, 'data' => $students];
    }

    /**
     * Bulk assign students to the group
     */
    public function bulkAssignToGroup(array $studentIds, int $groupId, int $courseId): array
    {
        $studentsData = DB::table('enrollments')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->leftJoin('orders', 'enrollments.order_id', '=', 'orders.id')
            ->where('enrollments.course_id', $courseId)
            ->whereIn('enrollments.student_id', $studentIds)
            ->select('students.id', 'students.full_name', 'orders.status as order_status')
            ->get();

        $paidStudentIds = [];
        $unpaidStudents = [];

        foreach ($studentsData as $student) {
            if ($student->order_status === 'completed') {
                $paidStudentIds[] = $student->id;
            } else {
                $unpaidStudents[] = ['id' => $student->id, 'full_name' => $student->full_name];
            }
        }

        if (!empty($paidStudentIds)) {
            DB::table('enrollments')
                ->where('course_id', $courseId)
                ->whereIn('student_id', $paidStudentIds)
                ->update(['group_id' => $groupId, 'updated_at' => now()]);

            DB::table('learning_groups')
                ->where('id', $groupId)
                ->update([
                    'enrolled_students' => DB::table('enrollments')->where('group_id', $groupId)->count(),
                ]);
        }

        return [
            'success'        => true,
            'assigned_count' => count($paidStudentIds),
            'unpaid_students' => $unpaidStudents,
        ];
    }

    /**
     * Bulk-mark selected students' enrollments as completed
     */
    public function bulkCompleteStudents(array $studentIds, int $groupId): array
    {
        $group = DB::table('learning_groups')->where('id', $groupId)->first();

        if (!$group) {
            return ['success' => false, 'status' => 404, 'message' => 'Group not found.'];
        }

        $completedCount = DB::table('enrollments')
            ->where('course_id', $group->course_id)
            ->where('group_id', $groupId)
            ->whereIn('student_id', $studentIds)
            ->where('is_completed', false)
            ->update([
                'is_completed' => true,
                'completed_at' => now(),
                'updated_at'   => now(),
            ]);

        return ['success' => true, 'completed_count' => $completedCount];
    }
}
