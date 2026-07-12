<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseInstructor;
use App\Models\Enrollment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CourseInstructorSync
{
    /**
     * @param  array<int>  $instructorIds  Ordered list — index becomes sort_order.
     */
    public function sync(Course $course, array $instructorIds): void
    {
        $instructorIds = collect($instructorIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($instructorIds === []) {
            throw ValidationException::withMessages([
                'instructor_ids' => ['At least one instructor is required.'],
            ]);
        }

        $currentPivotIds = $course->courseInstructors()
            ->pluck('instructor_id', 'id');

        $pivotData = collect($instructorIds)->mapWithKeys(fn ($id, $index) => [
            $id => ['sort_order' => $index],
        ])->all();

        $removingInstructorIds = $currentPivotIds
            ->filter(fn ($instructorId) => ! in_array($instructorId, $instructorIds, true))
            ->keys();

        if ($removingInstructorIds->isNotEmpty()) {
            $this->assertPivotRowsCanBeRemoved($removingInstructorIds);
        }

        $course->instructors()->sync($pivotData);

        $course->update([
            'instructor_id' => $instructorIds[0],
        ]);
    }

    /**
     * @param  Collection<int, int>  $pivotRowIds
     */
    private function assertPivotRowsCanBeRemoved(Collection $pivotRowIds): void
    {
        $blockedByGroups = CourseInstructor::query()
            ->whereIn('id', $pivotRowIds)
            ->whereHas('learningGroups')
            ->with('instructor:id,full_name')
            ->get();

        if ($blockedByGroups->isNotEmpty()) {
            $names = $blockedByGroups->pluck('instructor.full_name')->filter()->join(', ');

            throw ValidationException::withMessages([
                'instructor_ids' => ["Cannot remove instructor(s) assigned to learning groups: {$names}."],
            ]);
        }

        $blockedByReviews = CourseInstructor::query()
            ->whereIn('id', $pivotRowIds)
            ->whereHas('reviewRatings')
            ->with('instructor:id,full_name')
            ->get();

        if ($blockedByReviews->isNotEmpty()) {
            $names = $blockedByReviews->pluck('instructor.full_name')->filter()->join(', ');

            throw ValidationException::withMessages([
                'instructor_ids' => ["Cannot remove instructor(s) with existing review ratings: {$names}."],
            ]);
        }
    }

    public static function formatInstructorsCollection(Course $course): array
    {
        if (! $course->relationLoaded('instructors')) {
            return [];
        }

        return $course->instructors->map(function ($instructor) {
            return [
                'course_instructor_id' => $instructor->pivot->id,
                'id' => $instructor->id,
                'full_name' => $instructor->full_name,
                'name' => $instructor->full_name,
                'field' => $instructor->field,
                'bio' => $instructor->bio,
                'avatar' => $instructor->avatar,
                'phone' => $instructor->phone,
                'sort_order' => (int) ($instructor->pivot->sort_order ?? 0),
            ];
        })->values()->all();
    }

    /**
     * Resolve which instructors a student should see for a course.
     * When enrolled in a learning group, only that group's instructor is returned.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function resolveForEnrollment(Course $course, ?Enrollment $enrollment): array
    {
        $all = self::formatInstructorsCollection($course);

        if ($all === [] || ! $enrollment?->group_id) {
            return $all;
        }

        $courseInstructorId = $enrollment->relationLoaded('learningGroup') && $enrollment->learningGroup
            ? $enrollment->learningGroup->course_instructor_id
            : $enrollment->learningGroup()->value('course_instructor_id');

        if (! $courseInstructorId) {
            return $all;
        }

        $filtered = array_values(array_filter(
            $all,
            fn (array $instructor) => (int) $instructor['course_instructor_id'] === (int) $courseInstructorId
        ));

        return $filtered !== [] ? $filtered : $all;
    }

    /**
     * @return array<int, int>
     */
    public static function allowedCourseInstructorIdsForEnrollment(Course $course, ?Enrollment $enrollment): array
    {
        return array_values(array_map(
            fn (array $instructor) => (int) $instructor['course_instructor_id'],
            self::resolveForEnrollment($course, $enrollment)
        ));
    }
}
