<?php

namespace App\Services\Admin;

use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\LearningGroup;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminReviewService
{
    /**
     * Display a listing of the reviews with eager loading and filtering.
     */
    public function index(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        return CourseReview::with(['course', 'student', 'instructor'])
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($q) use ($search) {
                    $q->whereHas('course', function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%");
                    })
                        ->orWhereHas('student', function ($q) use ($search) {
                            $q->where('full_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(isset($filters['review_status']) && $filters['review_status'] !== '', function ($query) use ($filters) {
                $query->where('review_status', $filters['review_status']);
            })
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Merged listing: reviewed students + completed students without a review.
     */
    public function indexByGroup(int $groupId, int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        $group = LearningGroup::with('course:id,title')->findOrFail($groupId);
        $courseTitle = $group->course?->title;

        $enrollments = Enrollment::query()
            ->where('group_id', $groupId)
            ->with('student:id,full_name')
            ->get();

        $studentIds = $enrollments->pluck('student_id')->filter()->unique()->values();

        $reviewsByStudent = CourseReview::with(['course', 'student', 'instructor'])
            ->where('course_id', $group->course_id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $rows = collect();

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;

            if (! $student) {
                continue;
            }

            $review = $reviewsByStudent->get($enrollment->student_id);

            if ($review) {
                $rows->push($this->formatReviewRow($review));
            } elseif ($enrollment->is_completed) {
                $rows->push($this->formatNotReviewedRow($student, $courseTitle));
            }
        }

        $rows = $this->applyMergedFilters($rows, $filters);

        $rows = $rows->sortByDesc(fn (array $row) => $row['has_review'] ? ($row['created_at'] ?? '') : '')
            ->values();

        return $this->paginateCollection($rows, $perPage);
    }

    /**
     * Display the specified review.
     */
    public function show(CourseReview $review): CourseReview
    {
        return $review->load(['course', 'student', 'instructor']);
    }

    /**
     * Update the specified review in storage.
     */
    public function update(CourseReview $review, array $data): CourseReview
    {
        if (array_key_exists('review_status', $data)) {
            $review->update([
                'review_status' => $data['review_status'],
            ]);
        }

        return $review->load(['course', 'student', 'instructor']);
    }

    /**
     * Remove the specified review from storage.
     */
    public function destroy(CourseReview $review): bool
    {
        return (bool) $review->delete();
    }

    private function formatReviewRow(CourseReview $review): array
    {
        return [
            'id'                => $review->id,
            'course_id'         => $review->course_id,
            'student_id'        => $review->student_id,
            'review_status'     => $review->review_status,
            'instructor_id'     => $review->instructor_id,
            'content_rating'    => $review->content_rating,
            'instructor_rating' => $review->instructor_rating,
            'center_rating'     => $review->center_rating,
            'rating'            => $review->rating,
            'overall_comment'   => $review->overall_comment,
            'course_title'      => $review->course?->title,
            'student_name'      => $review->student?->full_name,
            'instructor_name'   => $review->instructor?->full_name,
            'created_at'        => $review->created_at?->format('Y-m-d H:i:s'),
            'updated_at'        => $review->updated_at?->format('Y-m-d H:i:s'),
            'has_review'        => true,
        ];
    }

    private function formatNotReviewedRow($student, ?string $courseTitle): array
    {
        return [
            'id'                => null,
            'course_id'         => null,
            'student_id'        => $student->id,
            'review_status'     => 'not_reviewed',
            'instructor_id'     => null,
            'content_rating'    => null,
            'instructor_rating' => null,
            'center_rating'     => null,
            'rating'            => null,
            'overall_comment'   => null,
            'course_title'      => $courseTitle,
            'student_name'      => $student->full_name,
            'instructor_name'   => null,
            'created_at'        => null,
            'updated_at'        => null,
            'has_review'        => false,
        ];
    }

    private function applyMergedFilters(Collection $rows, array $filters): Collection
    {
        if (! empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);

            $rows = $rows->filter(function (array $row) use ($search) {
                $studentName = mb_strtolower($row['student_name'] ?? '');
                $courseTitle = mb_strtolower($row['course_title'] ?? '');
                $comment = mb_strtolower($row['overall_comment'] ?? '');

                return str_contains($studentName, $search)
                    || str_contains($courseTitle, $search)
                    || str_contains($comment, $search);
            });
        }

        if (isset($filters['review_status']) && $filters['review_status'] !== '') {
            $status = $filters['review_status'];

            if ($status === 'not_reviewed') {
                $rows = $rows->filter(fn (array $row) => ! $row['has_review']);
            } else {
                $rows = $rows->filter(
                    fn (array $row) => $row['has_review'] && $row['review_status'] === $status
                );
            }
        }

        return $rows->values();
    }

    private function paginateCollection(Collection $rows, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) request()->query('page', 1));
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path'  => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
