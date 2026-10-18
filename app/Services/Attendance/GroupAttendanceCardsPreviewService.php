<?php

namespace App\Services\Attendance;

use App\Http\Resources\Admin\AdminStudentAttendanceCardResource;
use App\Models\Course;
use App\Models\LearningGroup;
use Illuminate\Support\Collection;

class GroupAttendanceCardsPreviewService
{
    public const FUTURE_BATCH_MAX = 50;

    public function __construct(
        private GroupAttendanceCardEligibilityService $eligibilityService,
    ) {}

    /**
     * Read-only preview of group members eligible for bulk attendance cards (excludes online enrollments).
     *
     * @return array<string, mixed>
     */
    public function preview(LearningGroup $group): array
    {
        $group->loadMissing('course:id,title');

        $enrollments = $this->eligibilityService->fetchGroupEnrollments($group);

        $onlineRows = $enrollments->filter(
            fn (object $row): bool => $row->attendance_type === Course::ATTENDANCE_TYPE_ONLINE
        )->values();

        $eligibleRows = $enrollments->filter(
            fn (object $row): bool => $row->attendance_type !== Course::ATTENDANCE_TYPE_ONLINE
        )->values();

        $studentIds = $eligibleRows->pluck('student_id')->unique()->values()->all();

        $activeCardsByStudentId = $this->eligibilityService->loadActiveCardsByStudentId($studentIds);

        $students = $eligibleRows->map(function (object $row) use ($activeCardsByStudentId): array {
            $card = $activeCardsByStudentId->get($row->student_id);
            $studentStatus = $row->student_status ?? 'active';
            $warnings = [];

            if ($studentStatus !== 'active') {
                $warnings[] = [
                    'code' => 'STUDENT_INACTIVE',
                    'message' => 'Automatic QR attendance scanning is blocked while this student\'s status is inactive.',
                ];
            }

            return [
                'student_id' => (int) $row->student_id,
                'full_name' => $row->full_name,
                'enrollment_number' => $row->enrollment_number,
                'email' => $row->email,
                'phone' => $row->phone,
                'student_status' => $studentStatus,
                'enrollment' => [
                    'is_completed' => (bool) $row->is_completed,
                    'completed_at' => $row->completed_at,
                    'attendance_type' => $row->attendance_type,
                ],
                'has_active_card' => $card !== null,
                'card' => $card !== null ? new AdminStudentAttendanceCardResource($card) : null,
                'warnings' => $warnings,
            ];
        })->values()->all();

        $withActiveCard = collect($students)->where('has_active_card', true)->count();
        $inactiveStudents = collect($students)->where('student_status', '!=', 'active')->count();

        return [
            'group_id' => $group->id,
            'group_name' => $group->group_name,
            'course_id' => $group->course_id,
            'course_title' => $group->course?->title,
            'future_batch_max' => self::FUTURE_BATCH_MAX,
            'counts' => [
                'total_enrollments' => $enrollments->count(),
                'eligible' => $eligibleRows->count(),
                'excluded_online' => $onlineRows->count(),
                'with_active_card' => $withActiveCard,
                'without_active_card' => $eligibleRows->count() - $withActiveCard,
                'inactive_students' => $inactiveStudents,
            ],
            'excluded_online_students' => $onlineRows->map(fn (object $row): array => [
                'student_id' => (int) $row->student_id,
                'full_name' => $row->full_name,
                'attendance_type' => $row->attendance_type,
            ])->values()->all(),
            'students' => $students,
        ];
    }

}
