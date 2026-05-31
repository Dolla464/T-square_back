<?php

namespace App\Services\Admin;

use App\Models\CourseReview;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminReviewService
{
    /**
     * Display a listing of the reviews with eager loading and filtering.
     */
    public function index(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        return CourseReview::with(['course', 'student', 'instructor'])
            // استخدام when لتطبيق بحث الـ Scopes بشكل نظيف
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];

                // تجميع شروط البحث بداخل أقواس لحماية بقية الفلاتر
                $query->where(function ($q) use ($search) {
                    $q->whereHas('course', function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%");
                    })
                        ->orWhereHas('student', function ($q) use ($search) {
                            $q->where('full_name', 'like', "%{$search}%");
                        });
                });
            })
            // التحقق من الـ review_status بشكل آمن حتى لو كانت القيمة 0 أو string
            ->when(isset($filters['review_status']) && $filters['review_status'] !== '', function ($query) use ($filters) {
                $query->where('review_status', $filters['review_status']);
            })
            ->latest()
            ->paginate($perPage);
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
        // استخدام array_key_exists أو التحديث المباشر للقيم الممررة فقط
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
}
