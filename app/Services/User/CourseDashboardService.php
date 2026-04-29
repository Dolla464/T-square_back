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
     * جلب كل بيانات الداشبورد: الإحصائيات + قائمة الكورسات
     *
     * @param int   $studentId  معرّف الطالب الحالي
     * @param array $filters    ['search' => string|null, 'status' => 'all'|'in_progress'|'completed']
     */
    public function getDashboardData(int $studentId, array $filters): array
    {
        return [
            'stats'   => $this->getStats($studentId),
            'courses' => $this->getCourses($studentId, $filters),
        ];
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    /**
     * حساب الإحصائيات عبر queries 
     */
    private function getStats(int $studentId): array
    {
        // كل الكورسات المنشورة
        $totalPlatformCourses = Course::active()->count();

        // الإحصائيات بتاعت بالطالب (Query Builder مباشر بدون Hydration)
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
            'total_enrolled'         => (int) ($enrollmentStats->total_enrolled ?? 0),
            'in_progress'            => (int) ($enrollmentStats->in_progress ?? 0),
            'completed'              => (int) ($enrollmentStats->completed ?? 0),
        ];
    }

    // -------------------------------------------------------------------------
    // Courses List
    // -------------------------------------------------------------------------

    /**
     * هات أحدث الكورسات مع الفلترة
     */
    private function getCourses(int $studentId, array $filters): Collection
    {
        $search = $this->normalizeSearch($filters['search'] ?? null);
        $status = $this->normalizeStatus($filters['status'] ?? null);

        $query = Course::active()
            ->with([
                'instructor:id,full_name,field',
            ])

            // ── Enrollment بتاعت بالطالب الحالي فقط (Constrained Eager Load) ──
            ->with([
                'enrollments' => fn ($q) => $q
                    ->select('id', 'course_id', 'student_id', 'is_completed', 'completed_at')
                    ->where('student_id', $studentId),
            ])

            // ── نحدد الأعمدة التي نحتاجها بس من جدول courses ─────────────
            ->select([
                'id',
                'title',
                'thumbnail',
                'google_drive_link',
                'instructor_id',
                'created_at',
            ]);

        // ── تطبيق الفلاتر ──────────────────────────────────────────────────
        $query = $this->applyFilters($query, $studentId, $search, $status);

        // ── أحدث LATEST_LIMIT كورس بس  ─────────────────────
        return $query
            ->latest()                      // ORDER BY created_at DESC
            ->limit(self::LATEST_LIMIT)     // LIMIT 10
            ->get();
    }

    /**
     * الفلتره
     */
    private function applyFilters(Builder $query, int $studentId, ?string $search, string $status): Builder
    {
        // ── فلتر البحث بالعنوان ────────────────────────────────────────────
        if ($search !== null) {
            $query->where('title', 'like', '%' . $search . '%');
        }

        // ── فلتر الحالة (all / in_progress / completed) ────────────────────
        if ($status === self::STATUS_IN_PROGRESS) {
            // الكورسات اللي اشترك فيها الطالب مخلصهاش بعد
            $query->whereHas('enrollments', fn ($q) =>
                $q->where('student_id', $studentId)
                  ->where('is_completed', false)
            );
        } elseif ($status === self::STATUS_COMPLETED) {
            // الكورسات اللي  الطالب خلصها
            $query->whereHas('enrollments', fn ($q) =>
                $q->where('student_id', $studentId)
                  ->where('is_completed', true)
            );
        }

        return $query;
    }

    /**
     * تنظيف قيمة البحث: trim + null لو فاضية
     */
    private function normalizeSearch(mixed $search): ?string
    {
        if (!is_string($search)) {
            return null;
        }

        $search = trim($search);

        return $search === '' ? null : $search;
    }

    /**
     * ضمان حالة صحيحة فقط
     */
    private function normalizeStatus(mixed $status): string
    {
        if (!is_string($status)) {
            return self::STATUS_ALL;
        }

        return match ($status) {
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_ALL => $status,
            default => self::STATUS_ALL,
        };
    }
}