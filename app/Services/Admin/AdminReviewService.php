<?php

namespace App\Services\Admin;

use App\Models\CourseReview;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminReviewService
{
    /**
     * Display a listing of the reviews.
     */
    public function index(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        $query = CourseReview::with(['course', 'student', 'instructor']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('course', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            })->orWhereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
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
        $review->update($data);

        return $review->load(['course', 'student', 'instructor']);
    }

    /**
     * Remove the specified review from storage.
     */
    public function destroy(CourseReview $review): bool
    {
        return $review->delete();
    }
}
