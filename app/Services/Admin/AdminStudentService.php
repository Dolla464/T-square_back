<?php

namespace App\Services\Admin;

use App\Http\Resources\Admin\AdminStudentResource;
use App\Models\Enrollment;
use App\Models\LearningGroup;
use App\Models\Student;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminStudentService
{
    use HandleImageUploadTrait;

    /**
     * Get all students paginated.
     */
    public function index(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        // define the basic relations to be loaded for the Resource
        $relations = [
            'user:id,email,email_verified_at',
            'enrollments.learningGroup',
            'enrollments.course.instructor',
            'enrollments.course.learningGroups'
        ];

        // if there is a group_id filter, filter the loaded relations to get the first group
        if (!empty($filters['group_id'])) {
            $relations['enrollments'] = function ($query) use ($filters) {
                $query->orderByRaw('group_id = ? DESC', [$filters['group_id']])
                    ->latest();
            };
        }

        return Student::with($relations)
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($u) use ($search) {
                            $u->where('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when(isset($filters['gender']) && $filters['gender'] !== '', function ($query) use ($filters) {
                $query->where('gender', $filters['gender']);
            })
            ->when(!empty($filters['group_id']), function ($query) use ($filters) {
                // filter the students who have this group
                $query->whereHas('enrollments', function ($q) use ($filters) {
                    $q->where('group_id', $filters['group_id']);
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get a single student.
     */
    public function show(Student $student): Student
    {
        return $student->load([
            'user',
            'enrollments.course.learningGroups:id,course_id,group_name',
            'enrollments.learningGroup:id,group_name',  // per-enrollment group
        ]);
    }

    /**
     * Update the student.
     */
    public function update(Student $student, array $data): Student
    {
        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $data['avatar'] = $this->uploadImage(
                $data['avatar'],
                'students',
                $student->getRawOriginal('avatar')
            );
        } else {
            // remove the avatar field from the array if no new file was uploaded
            unset($data['avatar']);
        }

        $student->update($data);

        return $student->load('user:id,email');
    }

    /**
     * Delete the student.
     */
    public function destroy(Student $student): void
    {
        $avatar = $student->getRawOriginal('avatar');
        if ($avatar && Storage::disk('public')->exists($avatar)) {
            Storage::disk('public')->delete($avatar);
        }

        $student->delete();
    }

    /**
     * Update the status of the student.
     */
    public function updateStatus(Student $student, string $status)
    {
        $student->update(['status' => $status]);
        return $student;
    }

    /**
     * Toggle the verification status of the student.
     */
    public function toggleVerify(Student $student)
    {
        $user = $student->user;
        $user->email_verified_at = $user->email_verified_at ? null : now();
        $user->save();

        return $user;
    }

    /**
     * Update the course group of the student.
     */
    public function updateCourseGroup(Student $student, int $courseId, int $newGroupId): bool
    {
        $enrollment = $student->enrollments()->where('course_id', $courseId)->first();

        if (!$enrollment || $enrollment->is_completed) {
            return false;
        }

        // group_id now belongs to the enrollment, not the student
        return $enrollment->update(['group_id' => $newGroupId]);
    }

    /**
     * Undate the course status of the student.
     */
    public function updateCourseStatus(Student $student, int $courseId, bool $isCompleted): bool
    {
        // Search for the enrollment record for this course for the student
        $enrollment = $student->enrollments()->where('course_id', $courseId)->first();

        if ($enrollment) {
            return $enrollment->update([
                'is_completed' => $isCompleted,
                'completed_at' => $isCompleted ? now() : null // If it becomes completed, log the time, and if it is cancelled, reset it
            ]);
        }

        return false;
    }
}
