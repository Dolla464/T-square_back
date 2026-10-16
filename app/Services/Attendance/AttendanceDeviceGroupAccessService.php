<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\Instructor;
use App\Models\LearningGroup;

class AttendanceDeviceGroupAccessService
{
    public function deviceCanAccessGroup(AttendanceDevice $device, LearningGroup $group): bool
    {
        if ($device->branch_id !== null && $group->branch_id !== null && $device->branch_id !== $group->branch_id) {
            return false;
        }

        if ($device->instructor_id !== null) {
            $instructor = $device->instructor;

            if (! $instructor || ! $this->instructorCanAccessGroup($group, $instructor)) {
                return false;
            }
        }

        return true;
    }

    public function instructorCanAccessGroup(LearningGroup $group, Instructor $instructor): bool
    {
        $group->loadMissing('course.instructors', 'courseInstructor');

        if ($group->courseInstructor?->instructor_id === $instructor->id) {
            return true;
        }

        return (bool) $group->course?->hasInstructor($instructor->id);
    }
}
