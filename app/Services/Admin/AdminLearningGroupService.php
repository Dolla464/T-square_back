<?php

namespace App\Services\Admin;

use App\Models\LearningGroup;
use Illuminate\Support\Facades\DB;

class AdminLearningGroupService
{
    /**
     * Get all groups paginated
     */
    public function getAllGroups($perPage = 10)
    {
        // load the relationships to the course and instructor to the resource
        return LearningGroup::with(['course:id,title', 'instructor:id,full_name'])
            ->withCount('students')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get the selection data for the frontend
     */
    public function getSelection()
    {
        // return only the id and name for the filters
        return LearningGroup::select('id', 'group_name')->get()->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->group_name
            ];
        });
    }

    /**
     * Create a new group
     */
    public function createGroup(array $data)
    {
        return LearningGroup::create($data);
    }

    /**
     * Update the group
     */
    public function updateGroup(LearningGroup $group, array $data)
    {
        $group->update($data);
        return $group;
    }

    /**
     * Delete the group
     */
    public function deleteGroup(LearningGroup $group)
    {
        return $group->delete();
    }

    /**
     * Get the unassigned and paid students for a specific course
     */
    public function getUnassignedCourseStudents(int $groupId): array
    {
        // 1. Get the group data to know the course it belongs to
        $group = DB::table('learning_groups')->where('id', $groupId)->first();

        if (!$group) {
            return ['success' => false, 'status' => 404, 'message' => 'Group not found.'];
        }

        // 2. Get the paid students (order_id completed) and not assigned to any group for this course
        $students = DB::table('enrollments')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('orders', 'enrollments.order_id', '=', 'orders.id')
            ->where('enrollments.course_id', $group->course_id)
            ->whereNull('enrollments.group_id')
            ->where('orders.status', 'completed') // Financial security condition 
            ->select(
                'students.id',
                'students.full_name',
                'students.phone',
                'enrollments.created_at as enrolled_at'
            )
            ->get()
            ->toArray();

        return [
            'success' => true,
            'status' => 200,
            'data' => $students
        ];
    }

    /**
     * Bulk assign students to the group after the financial verification and update the counter
     */
    public function bulkAssignToGroup(array $studentIds, int $groupId, int $courseId): array
    {
        // 1. Filter the students: get the students who have a completed order only from the sent list
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
                $unpaidStudents[] = [
                    'id' => $student->id,
                    'full_name' => $student->full_name
                ];
            }
        }

        // 2. Update the paid students once
        if (!empty($paidStudentIds)) {
            DB::table('enrollments')
                ->where('course_id', $courseId)
                ->whereIn('student_id', $paidStudentIds)
                ->update([
                    'group_id' => $groupId,
                    'updated_at' => now()
                ]);

            // 3. Update the smart counter for the students inside the group
            DB::table('learning_groups')
                ->where('id', $groupId)
                ->update([
                    'enrolled_students' => DB::table('enrollments')->where('group_id', $groupId)->count()
                ]);
        }

        return [
            'success' => true,
            'assigned_count' => count($paidStudentIds),
            'unpaid_students' => $unpaidStudents
        ];
    }

    /**
     * Bulk-mark selected students' enrollments as completed for this group's course.
     *
     * Only touches rows where:
     *   - enrollment belongs to this group   (enrollments.group_id = $groupId)
     *   - student is in the sent list         (student_id IN $studentIds)
     *   - enrollment is not already completed (is_completed = false)
     *
     * @return array{success: bool, status?: int, message?: string, completed_count?: int}
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

        return [
            'success'         => true,
            'completed_count' => $completedCount,
        ];
    }
}
