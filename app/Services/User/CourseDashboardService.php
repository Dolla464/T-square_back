<?php

namespace App\Services\User;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CourseDashboardService
{
    private const LATEST_LIMIT = 10;

    private const STATUS_ALL = 'all';

    private const STATUS_IN_PROGRESS = 'in_progress';

    private const STATUS_COMPLETED = 'completed';

    /**
     * Get all dashboard data: statistics + courses list
     *
     * @param  int  $studentId  Current student ID
     * @param  array  $filters  ['search' => string|null, 'status' => 'all'|'in_progress'|'completed']
     */
    public function getDashboardData(int $studentId, array $filters): array
    {
        return [
            'stats' => $this->getStats($studentId),
            'courses' => $this->getCourses($studentId, $filters),
        ];
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    /**
     * Calculate the statistics through queries
     */
    private function getStats(int $studentId): array
    {
        // All published courses
        $totalPlatformCourses = Course::active()->count();

        // The statistics of the student (Query Builder directly without Hydration)
        $enrollmentStats = Enrollment::query()
            ->selectRaw('
                COUNT(*)                                   AS total_enrolled,
                COALESCE(SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END), 0) AS in_progress,
                COALESCE(SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END), 0) AS completed
            ')
            ->where('student_id', $studentId)
            ->toBase()
            ->first();

        return [
            'total_platform_courses' => $totalPlatformCourses,
            'total_enrolled' => (int) ($enrollmentStats->total_enrolled ?? 0),
            'in_progress' => (int) ($enrollmentStats->in_progress ?? 0),
            'completed' => (int) ($enrollmentStats->completed ?? 0),
        ];
    }

    // -------------------------------------------------------------------------
    // Courses List
    // -------------------------------------------------------------------------

    /**
     * Get the latest courses with the filters (only the courses that have been paid successfully)
     */
    private function getCourses(int $studentId, array $filters): Collection
    {
        $search = $this->normalizeSearch($filters['search'] ?? null);
        $status = $this->normalizeStatus($filters['status'] ?? null);

        $query = Course::active()
            // ── 1. الفلترة الصارمة: لا تجلب الكورس إلا إذا كان هناك سداد مكتمل ──
            ->whereHas('enrollments', function ($q) use ($studentId) {
                $q->where('student_id', $studentId)
                    ->whereHas('order', function ($orderQuery) {
                        $orderQuery->where('status', 'completed'); // Payment completed condition
                    });
            })

            // ── 2. Get the usual relationships for the course (Eager Loading) ──
            ->with([
                'instructor:id,full_name,field,bio,avatar,phone',
                'tags:id,name,slug',
                'previews:id,course_id,title,video_url,description,video_provider,duration_seconds,sort_order',
            ])

            // ── Enrollment of the current student only ──
            ->with([
                'enrollments' => fn($q) => $q
                    ->select('id', 'course_id', 'student_id', 'order_id', 'is_completed', 'completed_at')
                    ->where('student_id', $studentId),
            ])

            // ── Define the columns we need from the courses table ─────────────
            ->select([
                'id',
                'title',
                'slug',
                'short_description',
                'description',
                'thumbnail',
                'google_drive_link',
                'instructor_id',
                'created_at',
            ]);

        // ── Apply the additional filters ───────────────────────────────────────────
        $query = $this->applyFilters($query, $studentId, $search, $status);

        // ── Get the latest LATEST_LIMIT courses ─────────────────────
        return $query
            ->latest()                      // ORDER BY created_at DESC
            ->limit(self::LATEST_LIMIT)     // LIMIT 10
            ->get();
    }

    /**
     * Filter the courses
     */
    private function applyFilters(Builder $query, int $studentId, ?string $search, string $status): Builder
    {
        // ── فلتر البحث بالعنوان ────────────────────────────────────────────
        if ($search !== null) {
            $query->where('title', 'like', '%' . $search . '%');
        }

        // ── فلتر الحالة (all / in_progress / completed) ────────────────────
        if ($status === self::STATUS_IN_PROGRESS) {
            // The courses the student enrolled in and completed them
            $query->whereHas(
                'enrollments',
                fn($q) => $q->where('student_id', $studentId)
                    ->where('is_completed', false)
            );
        } elseif ($status === self::STATUS_COMPLETED) {
            // The courses the student completed
            $query->whereHas(
                'enrollments',
                fn($q) => $q->where('student_id', $studentId)
                    ->where('is_completed', true)
            );
        }

        return $query;
    }

    /**
     * Clean the search value: trim + null if empty
     */
    private function normalizeSearch(mixed $search): ?string
    {
        if (! is_string($search)) {
            return null;
        }

        $search = trim($search);

        return $search === '' ? null : $search;
    }

    /**
     * Ensure a valid status only
     */
    private function normalizeStatus(mixed $status): string
    {
        if (! is_string($status)) {
            return self::STATUS_ALL;
        }

        return match ($status) {
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_ALL => $status,
            default => self::STATUS_ALL,
        };
    }

    /**
     * Get the details of a specific course fully for the selected student
     * * @param int $studentId
     * @param int $courseId
     * @return Course
     */
    public function getCourseDetails(int $studentId, int $courseId): Course
    {
        return Course::active()
            // Ensure the student has a completed payment for this course
            ->whereHas('enrollments', function ($q) use ($studentId, $courseId) {
                $q->where('student_id', $studentId)
                  ->where('course_id', $courseId)
                  ->whereHas('order', function ($orderQuery) {
                      $orderQuery->where('status', 'completed');
                  });
            })
            // Get the necessary relationships for displaying the course details fully
            ->with([
                'instructor:id,full_name,field,bio,avatar,phone',
                'category:id,name,slug',
                'tags:id,name,slug',
                'previews:id,course_id,title,video_url,description,video_provider,duration_seconds,sort_order',
                'learnings:id,course_id,title',
            ])
            // Get the enrollment data for this student only to read the completion status and date in the frontend
            ->with([
                'enrollments' => fn($q) => $q
                    ->select('id', 'course_id', 'student_id', 'order_id', 'is_completed', 'completed_at')
                    ->where('student_id', $studentId),
            ])
            // The columns we need for the full display code
            ->select([
                'id',
                'title',
                'slug',
                'short_description',
                'description',
                'thumbnail',
                'cover_image',
                'price_before',
                'discount_price',
                'price',
                'is_free',
                'level',
                'language',
                'duration_weeks',
                'duration_hours',
                'avg_rating',
                'total_reviews',
                'total_students',
                'category_id',
                'instructor_id',
                'google_drive_link',
                'created_at',
            ])
            ->findOrFail($courseId); // Throws 404 automatically if the course is not found or the student is not enrolled in it
    }
}
