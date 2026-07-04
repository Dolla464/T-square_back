<?php

namespace App\Services\Admin;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\LearningGroup;
use App\Http\Resources\Admin\LearningGroup\AdminLearningGroupResource;
use App\Notifications\CourseReviewRequired;
use App\Notifications\InstructorGroupAssignedNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
    public function getSelection(?int $courseId = null, ?string $status = null)
    {
        return LearningGroup::query()
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->select('id', 'group_name', 'course_id')
            ->orderBy('group_name')
            ->get()
            ->map(function ($group) {
                return [
                    'id'        => $group->id,
                    'name'      => $group->group_name,
                    'course_id' => $group->course_id,
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

            if (! empty($data['student_ids'])) {
                $studentSync = $this->syncGroupStudents($group, $data);
            }

            $group->refresh();
            $newStatus = $group->status;
            $syncResult = $this->syncEnrollmentsWithGroupStatus($group, 'active', $newStatus);
            $group->sync_meta = array_merge(
                $syncResult,
                $studentSync ?? ['skipped_student_ids' => []]
            );

            $group->load(['course:id,title', 'instructor:id,full_name', 'schedules']);

            // Notify the assigned instructor about the new group
            $instructorUser = $group->instructor?->user;
            if ($instructorUser) {
                $instructorUser->notify(new InstructorGroupAssignedNotification($group));
            }

            return new AdminLearningGroupResource($group);
        });
    }

    /**
     * Get single group details with its assigned students, relationships, and counts
     */
    public function getGroupDetails(LearningGroup $group): AdminLearningGroupResource
    {
        $group->load(['course:id,title', 'instructor:id,full_name', 'schedules']);

        $students = DB::table('enrollments')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('enrollments.group_id', $group->id)
            ->where('enrollments.course_id', $group->course_id)
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

        // #region agent log
        file_put_contents(
            base_path('debug-0d7cb1.log'),
            json_encode([
                'sessionId' => '0d7cb1',
                'hypothesisId' => 'F',
                'location' => 'AdminLearningGroupService.php:getGroupDetails',
                'message' => 'group students loaded',
                'data' => [
                    'groupId' => $group->id,
                    'courseId' => $group->course_id,
                    'studentCount' => $students->count(),
                    'uniqueStudentIds' => $students->pluck('id')->unique()->values()->all(),
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
                'runId' => 'post-fix',
            ]) . "\n",
            FILE_APPEND
        );
        // #endregion

        $group->setRelation('assigned_students', $students);

        return new AdminLearningGroupResource($group);
    }

    /**
     * Update group details, sync schedules, and regenerate attendance sessions
     */
    public function updateGroup(LearningGroup $group, array $data): AdminLearningGroupResource
    {
        return DB::transaction(function () use ($group, $data) {
            $oldStatus = $group->status;

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
                $activeSchedules = $this->syncGroupSchedules($group, $data['schedules']);
                $this->regenerateFutureSessions($group, $activeSchedules);
            }

            if (isset($data['student_ids'])) {
                $studentSync = $this->syncGroupStudents($group, $data);
            }

            $group->refresh();
            $newStatus = $group->status;
            $syncResult = $this->syncEnrollmentsWithGroupStatus($group, $oldStatus, $newStatus);
            $group->sync_meta = array_merge(
                $syncResult,
                $studentSync ?? ['skipped_student_ids' => []]
            );

            $group->load(['course:id,title', 'instructor:id,full_name', 'schedules', 'attendanceSessions']);

            return new AdminLearningGroupResource($group);
        });
    }

    /**
     * Sync enrollments when group status changes or when a completed group is saved.
     *
     * @return array{enrollments_completed: int, enrollments_reopened: int, notifications_sent: int}
     */
    public function syncEnrollmentsWithGroupStatus(
        LearningGroup $group,
        string $oldStatus,
        string $newStatus
    ): array {
        $result = [
            'enrollments_completed' => 0,
            'enrollments_reopened'  => 0,
            'notifications_sent'    => 0,
        ];

        if ($newStatus === 'completed') {
            $incompleteEnrollments = Enrollment::query()
                ->where('group_id', $group->id)
                ->where('course_id', $group->course_id)
                ->where('is_completed', false)
                ->get();

            $newlyCompleted = new Collection;

            foreach ($incompleteEnrollments as $enrollment) {
                $enrollment->markAsCompleted();
                $newlyCompleted->push($enrollment->fresh());
                $result['enrollments_completed']++;
            }

            if ($oldStatus !== 'completed' && $newlyCompleted->isNotEmpty()) {
                $result['notifications_sent'] = $this->notifyStudentsReviewRequired($group, $newlyCompleted);
            }
        }

        if ($oldStatus === 'completed' && $newStatus === 'active') {
            $completedEnrollments = Enrollment::query()
                ->where('group_id', $group->id)
                ->where('course_id', $group->course_id)
                ->where('is_completed', true)
                ->get();

            foreach ($completedEnrollments as $enrollment) {
                $enrollment->update([
                    'is_completed' => false,
                    'completed_at' => null,
                ]);
                $result['enrollments_reopened']++;
            }
        }

        return $result;
    }

    /**
     * Notify students who were newly marked completed to submit a review.
     */
    private function notifyStudentsReviewRequired(LearningGroup $group, Collection $enrollments): int
    {
        $sent = 0;

        foreach ($enrollments as $enrollment) {
            $enrollment->loadMissing(['student.user', 'course']);

            $hasReview = CourseReview::query()
                ->where('course_id', $enrollment->course_id)
                ->where('student_id', $enrollment->student_id)
                ->exists();

            if ($hasReview) {
                continue;
            }

            $user = $enrollment->student?->user;

            if ($user) {
                $user->notify(new CourseReviewRequired($enrollment, $group));
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Assign students to the group and apply per-student completion flags from the payload.
     *
     * @return array{skipped_student_ids: int[]}
     */
    private function syncGroupStudents(LearningGroup $group, array $data): array
    {
        $incomingStudentIds = array_map('intval', array_filter((array) ($data['student_ids'] ?? [])));
        $studentStatuses = $data['student_statuses'] ?? [];
        $skippedStudentIds = [];

        DB::table('enrollments')
            ->where('group_id', $group->id)
            ->where('course_id', $group->course_id)
            ->whereNotIn('student_id', $incomingStudentIds)
            ->update(['group_id' => null, 'updated_at' => now()]);

        foreach ($incomingStudentIds as $studentId) {
            $isCompleted = (bool) ($studentStatuses[$studentId]
                ?? $studentStatuses[(string) $studentId]
                ?? false);

            // #region agent log
            $existingEnrollment = DB::table('enrollments')
                ->where('student_id', $studentId)
                ->where('course_id', $group->course_id)
                ->first();
            file_put_contents(
                base_path('debug-0d7cb1.log'),
                json_encode([
                    'sessionId' => '0d7cb1',
                    'hypothesisId' => 'A',
                    'location' => 'AdminLearningGroupService.php:syncGroupStudents',
                    'message' => 'before enrollment update',
                    'data' => [
                        'studentId' => $studentId,
                        'courseId' => $group->course_id,
                        'groupId' => $group->id,
                        'enrollmentExists' => $existingEnrollment !== null,
                        'willInsert' => false,
                        'enrollment' => $existingEnrollment ? [
                            'id' => $existingEnrollment->id,
                            'price_paid' => $existingEnrollment->price_paid,
                            'order_id' => $existingEnrollment->order_id,
                            'group_id' => $existingEnrollment->group_id,
                        ] : null,
                    ],
                    'timestamp' => (int) round(microtime(true) * 1000),
                    'runId' => 'post-fix',
                ]) . "\n",
                FILE_APPEND
            );
            // #endregion

            $updated = DB::table('enrollments')
                ->where('student_id', $studentId)
                ->where('course_id', $group->course_id)
                ->update([
                    'group_id'     => $group->id,
                    'is_completed' => $isCompleted,
                    'completed_at' => $isCompleted ? now() : null,
                    'updated_at'   => now(),
                ]);

            if (!$updated) {
                $skippedStudentIds[] = $studentId;
            }
        }

        $actualCount = DB::table('enrollments')
            ->where('group_id', $group->id)
            ->where('course_id', $group->course_id)
            ->count();

        DB::table('learning_groups')
            ->where('id', $group->id)
            ->update(['enrolled_students' => $actualCount]);

        return ['skipped_student_ids' => $skippedStudentIds];
    }

    /**
     * Delete the group
     */
    public function deleteGroup(LearningGroup $group): ?bool
    {
        return $group->delete();
    }

    /**
     * Generate attendance sessions for every schedule day between start and end date.
     */
    public function generateAttendanceSessions(LearningGroup $group): void
    {
        $group->refresh();
        $group->load('schedules');

        $this->generateAttendanceSessionsFrom(
            $group,
            $group->start_date->copy(),
            $group->end_date->copy(),
            $group->schedules,
            skipExisting: false
        );
    }

    /**
     * Generate sessions for a date range using the given schedule rows.
     */
    public function generateAttendanceSessionsFrom(
        LearningGroup $group,
        Carbon $fromDate,
        Carbon $toDate,
        $schedules,
        bool $skipExisting = false
    ): void {
        $dayMap      = self::dayOfWeekMap();
        $currentDate = $fromDate->copy();

        while ($currentDate->lte($toDate)) {
            foreach ($schedules as $schedule) {
                $carbonDay = $dayMap[$schedule->day_of_week] ?? null;

                if ($carbonDay === null || $currentDate->dayOfWeek !== $carbonDay) {
                    continue;
                }

                if ($skipExisting) {
                    $exists = AttendanceSession::where('learning_group_id', $group->id)
                        ->where('schedule_id', $schedule->id)
                        ->whereDate('session_date', $currentDate->toDateString())
                        ->exists();

                    if ($exists) {
                        continue;
                    }
                }

                AttendanceSession::create([
                    'learning_group_id' => $group->id,
                    'schedule_id'       => $schedule->id,
                    'session_date'      => $currentDate->copy(),
                    'status'            => 'upcoming',
                ]);
            }

            $currentDate->addDay();
        }
    }

    /**
     * Sync weekly schedule rows in-place (no mass delete) to preserve historical sessions.
     *
     * @return \Illuminate\Support\Collection Active schedule rows used for future generation
     */
    private function syncGroupSchedules(LearningGroup $group, array $newSchedules): \Illuminate\Support\Collection
    {
        $group->load('schedules');

        $existing = $group->schedules->keyBy('day_of_week');
        $newByDay = collect($newSchedules)->keyBy(fn ($s) => (int) $s['day_of_week']);
        $activeIds = [];

        foreach ($newSchedules as $scheduleData) {
            $day = (int) $scheduleData['day_of_week'];

            if ($existing->has($day)) {
                $schedule = $existing->get($day);
                $schedule->update([
                    'start_time' => $scheduleData['start_time'],
                    'end_time'   => $scheduleData['end_time'],
                    'room'       => $scheduleData['room'] ?? null,
                ]);
                $activeIds[] = $schedule->id;
            } else {
                $created = $group->schedules()->create($scheduleData);
                $activeIds[] = $created->id;
            }
        }

        foreach ($existing as $day => $schedule) {
            if ($newByDay->has($day)) {
                continue;
            }

            if ($this->scheduleHasProtectedSessions($schedule)) {
                continue;
            }

            $schedule->delete();
        }

        return $group->schedules()->whereIn('id', $activeIds)->get();
    }

    /**
     * Replace future upcoming sessions only; past and non-upcoming sessions are untouched.
     */
    private function regenerateFutureSessions(LearningGroup $group, $activeSchedules): void
    {
        $group->refresh();

        $cutoff  = Carbon::today();
        $endDate = $group->end_date->copy();
        $cutoffStr = $cutoff->toDateString();
        $endStr    = $endDate->toDateString();

        AttendanceSession::where('learning_group_id', $group->id)
            ->where('status', 'upcoming')
            ->whereRaw('COALESCE(override_date, session_date) > ?', [$endStr])
            ->delete();

        AttendanceSession::where('learning_group_id', $group->id)
            ->where('status', 'upcoming')
            ->whereRaw('COALESCE(override_date, session_date) >= ?', [$cutoffStr])
            ->delete();

        if ($activeSchedules->isEmpty()) {
            return;
        }

        $fromDate = $cutoff->copy()->max($group->start_date);

        if ($fromDate->gt($endDate)) {
            return;
        }

        $this->generateAttendanceSessionsFrom(
            $group,
            $fromDate,
            $endDate,
            $activeSchedules,
            skipExisting: true
        );
    }

    /**
     * A session is protected if it is in the past or no longer upcoming.
     */
    private function scheduleHasProtectedSessions($schedule): bool
    {
        $cutoff = Carbon::today()->toDateString();

        return AttendanceSession::where('schedule_id', $schedule->id)
            ->where(function ($q) use ($cutoff) {
                $q->whereRaw('COALESCE(override_date, session_date) < ?', [$cutoff])
                    ->orWhere('status', '!=', 'upcoming');
            })
            ->exists();
    }

    /** Map custom day (0=Sat … 6=Fri) to Carbon dayOfWeek. */
    private static function dayOfWeekMap(): array
    {
        return [0 => 6, 1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5];
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
            ->join('orders', 'enrollments.order_id', '=', 'orders.id')
            ->where('enrollments.course_id', $group->course_id)
            ->whereNull('enrollments.group_id')
            ->where('orders.status', 'completed')
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
                    'enrolled_students' => DB::table('enrollments')
                        ->where('group_id', $groupId)
                        ->where('course_id', $courseId)
                        ->count(),
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

    /**
     * Get enrolled students for a group (used by export).
     */
    public function getGroupStudentsForExport(LearningGroup $group): array
    {
        $group->load(['course:id,title', 'instructor:id,full_name']);

        $students = DB::table('enrollments')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('enrollments.group_id', $group->id)
            ->where('enrollments.course_id', $group->course_id)
            ->select(
                'students.id',
                'students.full_name',
                'users.email',
                'students.phone',
                'enrollments.is_completed',
            )
            ->orderBy('students.full_name')
            ->get();

        return [
            'group'    => $group,
            'students' => $students,
        ];
    }
}
