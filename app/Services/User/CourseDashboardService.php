<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Collection;

class CourseDashboardService
{
    private const LATEST_LIMIT = 10;

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

        // الإحصائيات بتاعت بالطالب
        $enrollmentStats = Enrollment::query()
            ->selectRaw('
                COUNT(*)                                   AS total_enrolled,
                SUM(CASE WHEN is_completed = 0 THEN 1 END) AS in_progress,
                SUM(CASE WHEN is_completed = 1 THEN 1 END) AS completed
            ')
            ->where('student_id', $studentId)
            ->first();

        return [
            'total_platform_courses' => $totalPlatformCourses,
            'total_enrolled'         => (int) ($enrollmentStats->total_enrolled ?? 0),
            'in_progress'            => (int) ($enrollmentStats->in_progress    ?? 0),
            'completed'              => (int) ($enrollmentStats->completed      ?? 0),
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
        $query = $this->applyFilters($query, $studentId, $filters);

        // ── أحدث LATEST_LIMIT كورس بس  ─────────────────────
        return $query
            ->latest()                      // ORDER BY created_at DESC
            ->limit(self::LATEST_LIMIT)     // LIMIT 10
            ->get();
    }

    /**
     * الفلتره
     */
    private function applyFilters($query, int $studentId, array $filters)
    {
        // ── فلتر البحث بالعنوان ────────────────────────────────────────────
        if (!empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        // ── فلتر الحالة (all / in_progress / completed) ────────────────────
        $status = $filters['status'] ?? 'all';

        if ($status === 'in_progress') {
            // الكورسات اللي اشترك فيها الطالب مخلصهاش بعد
            $query->whereHas('enrollments', fn ($q) =>
                $q->where('student_id', $studentId)
                  ->where('is_completed', false)
            );
        } elseif ($status === 'completed') {
            // الكورسات اللي  الطالب خلصها
            $query->whereHas('enrollments', fn ($q) =>
                $q->where('student_id', $studentId)
                  ->where('is_completed', true)
            );
        }

        return $query;
    }
}