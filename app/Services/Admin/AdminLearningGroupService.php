<?php

namespace App\Services\Admin;

use App\Models\LearningGroup;
use App\Http\Resources\Admin\LearningGroup\AdminLearningGroupResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminLearningGroupService
{
    /**
     * Get all groups paginated with dynamic search filter
     */
    public function getAllGroups($perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        $query = LearningGroup::with(['course:id,title', 'instructor:id,full_name'])
            ->withCount('students');

        // Apply the search filter if the value is present
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                // 1. Search in the group name itself
                $q->where('group_name', 'LIKE', "%{$search}%")
                    // 2. Search in the course name related to the group
                    ->orWhereHas('course', function ($courseQuery) use ($search) {
                        $courseQuery->where('title', 'LIKE', "%{$search}%");
                    });
            });
        }

        $groups = $query->latest()->paginate($perPage);

        // Convert the internal Collection to the Resource
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
                'id' => $group->id,
                'name' => $group->group_name
            ];
        });
    }

    /**
     * Create a new group and load relationships
     */
    public function createGroup(array $data): AdminLearningGroupResource
    {
        $group = LearningGroup::create($data);

        $group->load(['course:id,title', 'instructor:id,full_name']);

        return new AdminLearningGroupResource($group);
    }

    /**
     * Get single group details with its assigned students, relationships, and counts
     */
    public function getGroupDetails(LearningGroup $group): AdminLearningGroupResource
    {
        // 1. Load the basic relationships and counts
        $group->load(['course:id,title', 'instructor:id,full_name']);
        $group->loadCount('students');

        // 2. Get the students currently assigned to this group based on the enrollments
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

        // 3. Attach the students to the object as a dynamic relationship so the Resource can read it
        $group->setRelation('assigned_students', $students);

        return new AdminLearningGroupResource($group);
    }

    /**
     * Update group details and force sync students securely
     */
    public function updateGroup(LearningGroup $group, array $data): AdminLearningGroupResource
    {
        return DB::transaction(function () use ($group, $data) {
            // 1. Update the basic data for the group
            $group->update([
                'group_name'    => $data['group_name'],
                'course_id'     => $data['course_id'],
                'instructor_id' => $data['instructor_id'],
            ]);

            // 2. Extract the IDs and ensure they are converted to explicit numbers (Integers) to break the issue
            $incomingStudentIds = isset($data['student_ids']) ? (array)$data['student_ids'] : [];
            $incomingStudentIds = array_map('intval', array_filter($incomingStudentIds)); 
            
            $studentStatuses = $data['student_statuses'] ?? [];

            // 3. Detach the students who were deleted from the frontend (set the group_id to null)
            DB::table('enrollments')
                ->where('group_id', $group->id)
                ->whereNotIn('student_id', $incomingStudentIds)
                ->update([
                    'group_id'   => null,
                    'updated_at' => now()
                ]);

            // 4. Link the current and new students to the group explicitly and directly.
            //    updateOrInsert is used instead of a bare update() so that edge-case
            //    enrollment rows that don't yet exist are created rather than silently
            //    skipped (0 affected rows).
            if (!empty($incomingStudentIds)) {
                foreach ($incomingStudentIds as $studentId) {
                    // Resolve completion status — check both int and string key because
                    // JSON always serialises object keys as strings.
                    $isCompleted = false;
                    if (isset($studentStatuses[$studentId])) {
                        $isCompleted = (bool) $studentStatuses[$studentId];
                    } elseif (isset($studentStatuses[(string) $studentId])) {
                        $isCompleted = (bool) $studentStatuses[(string) $studentId];
                    }

                    // updateOrInsert(conditions, values): updates the row if it exists,
                    // inserts it otherwise — never silently returns 0 affected rows.
                    DB::table('enrollments')->updateOrInsert(
                        [
                            'student_id' => $studentId,
                            'course_id'  => $group->course_id,
                        ],
                        [
                            'group_id'     => $group->id,
                            'is_completed' => $isCompleted,
                            'completed_at' => $isCompleted ? now() : null,
                            'updated_at'   => now(),
                        ]
                    );
                }
            }

            // 5. Update the actual count directly in the learning_groups table based on what was actually recorded
            $actualCount = DB::table('enrollments')->where('group_id', $group->id)->count();
            
            DB::table('learning_groups')
                ->where('id', $group->id)
                ->update(['enrolled_students' => $actualCount]);

            // Reload the relationships for the current model before exiting the Transaction
            $group->refresh();
            $group->load(['course:id,title', 'instructor:id,full_name', 'students'])->loadCount('students');
            
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
     * Get the unassigned students for a specific course (Fixed for Admin & Paid students)
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
            // 1. Convert the Join to leftJoin to ensure no students are dropped who don't have an orders system (orders)
            ->leftJoin('orders', 'enrollments.order_id', '=', 'orders.id')
            ->where('enrollments.course_id', $group->course_id)
            ->whereNull('enrollments.group_id') // Must not be enrolled in any group currently
            // 2. Modify the financial condition: either the order is completed, or the student is added manually by the admin (order_id is null)
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

        return [
            'success' => true,
            'status' => 200,
            'data' => $students
        ];
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
                $unpaidStudents[] = [
                    'id' => $student->id,
                    'full_name' => $student->full_name
                ];
            }
        }

        if (!empty($paidStudentIds)) {
            DB::table('enrollments')
                ->where('course_id', $courseId)
                ->whereIn('student_id', $paidStudentIds)
                ->update([
                    'group_id' => $groupId,
                    'updated_at' => now()
                ]);

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

        return [
            'success'         => true,
            'completed_count' => $completedCount,
        ];
    }
}
