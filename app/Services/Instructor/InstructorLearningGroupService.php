<?php

namespace App\Services\Instructor;

use App\Models\LearningGroup;

class InstructorLearningGroupService
{
    /**
     * Get learning groups for dropdown selection, scoped to the instructor.
     */
    public function getSelectionForInstructor(int $instructorId)
    {
        return LearningGroup::forInstructor($instructorId)
            ->select('id', 'group_name')
            ->orderBy('group_name')
            ->get()
            ->map(fn ($group) => [
                'id'   => $group->id,
                'name' => $group->group_name,
            ]);
    }
}
