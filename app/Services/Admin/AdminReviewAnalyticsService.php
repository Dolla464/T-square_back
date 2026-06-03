<?php

namespace App\Services\Admin;

use App\Models\CourseReview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminReviewAnalyticsService
{
    /**
     * Get the analytics stats for the admin dashboard
     */
    public function getRecentStats(): array
    {
        $cacheKey = 'admin_dashboard_review_stats';

        return Cache::remember($cacheKey, now()->addHour(), function () {
            // ملاحظة: تم إزالة فلترة الوقت هنا لكي يحسب كل البيانات المتاحة مثل الـ JSON.
            // إذا كنت تريد آخر شهر فقط، قم بإلغاء تعليق السطرين القادمين وضعهما في الـ query.
            // $oneMonthAgo = now()->subMonth();

            $stats = CourseReview::query()
                // ->where('created_at', '>=', $oneMonthAgo) 
                ->select([
                    // 1. إجمالي المراجعات
                    DB::raw('COUNT(*) as total_reviews'),

                    // 2. متوسط التقييم للمقبولة فقط (تم تعديل الحقل لـ review_status)
                    DB::raw('AVG(CASE WHEN review_status = "accepted" THEN rating END) as average_rating'),

                    // 3. عدد الحالات بناءً على الـ review_status الصحيح
                    DB::raw('COUNT(CASE WHEN review_status = "pending" THEN 1 END) as pending_count'),
                    DB::raw('COUNT(CASE WHEN review_status = "rejected" THEN 1 END) as rejected_count'),
                ])
                ->first();

            return [
                'total_reviews'   => (int) ($stats->total_reviews ?? 0),
                'average_rating'  => $stats->average_rating ? round((float) $stats->average_rating, 1) : 0.0,
                'pending_count'   => (int) ($stats->pending_count ?? 0),
                'rejected_count'  => (int) ($stats->rejected_count ?? 0),
            ];
        });
    }
}