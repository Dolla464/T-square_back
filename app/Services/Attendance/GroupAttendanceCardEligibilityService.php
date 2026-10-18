<?php

namespace App\Services\Attendance;

use App\Models\Course;
use App\Models\LearningGroup;
use App\Models\GroupAttendanceCardBulkOperation;
use App\Models\GroupAttendanceCardBulkOperationStudent;
use App\Models\Student;
use App\Models\StudentAttendanceCard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared enrollment eligibility for group attendance card preview and bulk issuance.
 */
class GroupAttendanceCardEligibilityService
{
    /**
     * @return Collection<int, object>
     */
    public function fetchGroupEnrollments(LearningGroup $group): Collection
    {
        return DB::table('enrollments')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('enrollments.group_id', $group->id)
            ->where('enrollments.course_id', $group->course_id)
            ->select(
                'students.id as student_id',
                'students.full_name',
                'students.enrollment_number',
                'students.phone',
                'students.status as student_status',
                'users.email',
                'enrollments.is_completed',
                'enrollments.completed_at',
                'enrollments.attendance_type',
            )
            ->orderBy('students.full_name')
            ->get();
    }

    public function isEnrollmentEligibleForIssuance(object $row): bool
    {
        return $row->attendance_type !== Course::ATTENDANCE_TYPE_ONLINE;
    }

    /**
     * @param  list<int>  $studentIds
     * @return Collection<int, StudentAttendanceCard>
     */
    public function loadActiveCardsByStudentId(array $studentIds): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return StudentAttendanceCard::query()
            ->whereIn('student_id', $studentIds)
            ->active()
            ->orderByDesc('issued_at')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');
    }

    /**
     * Revalidate that the student is still enrolled in the group/course and not online-only.
     */
    public function findEligibleEnrollmentRow(LearningGroup $group, int $studentId): ?object
    {
        return $this->fetchGroupEnrollments($group)
            ->first(fn (object $row): bool => (int) $row->student_id === $studentId
                && $this->isEnrollmentEligibleForIssuance($row));
    }

    /**
     * @return list<array{code: string, message: string}>
     */
    public function inactiveStudentWarnings(Student $student): array
    {
        $status = $student->fresh()->status ?? $student->status;

        if ($status !== 'inactive') {
            return [];
        }

        return [
            [
                'code' => 'STUDENT_INACTIVE',
                'message' => 'Automatic QR attendance scanning is blocked while this student\'s status is inactive.',
            ],
        ];
    }

    public function resolvePlannedAction(string $mode, bool $hasActiveCard): string
    {
        return match ($mode) {
            GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY => $hasActiveCard
                ? GroupAttendanceCardBulkOperationStudent::PLANNED_SKIP
                : GroupAttendanceCardBulkOperationStudent::PLANNED_ISSUE,
            GroupAttendanceCardBulkOperation::MODE_REISSUE_ACTIVE_ONLY => $hasActiveCard
                ? GroupAttendanceCardBulkOperationStudent::PLANNED_REISSUE
                : GroupAttendanceCardBulkOperationStudent::PLANNED_SKIP,
            GroupAttendanceCardBulkOperation::MODE_ISSUE_ALL => $hasActiveCard
                ? GroupAttendanceCardBulkOperationStudent::PLANNED_REISSUE
                : GroupAttendanceCardBulkOperationStudent::PLANNED_ISSUE,
            default => GroupAttendanceCardBulkOperationStudent::PLANNED_SKIP,
        };
    }
}
