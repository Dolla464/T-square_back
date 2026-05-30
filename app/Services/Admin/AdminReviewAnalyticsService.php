<?php

namespace App\Services\Admin;

use App\Models\CourseReview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminReviewAnalyticsService
{
    /**
     * جلب وتكييش إحصائيات المراجعات لآخر شهر فقط 
     */
    public function getRecentStats(): array
    {
        $cacheKey = 'admin_dashboard_review_stats';

        return Cache::remember($cacheKey, now()->addHour(), function () {
            $oneMonthAgo = now()->subMonth();

            $stats = CourseReview::query()
                ->where('created_at', '>=', $oneMonthAgo)
                ->select([
                    // 1. إجمالي المراجعات للشهر
                    DB::raw('COUNT(*) as total_reviews'),

                    // 2. متوسط التقييم للمقبولين فقط
                    DB::raw('AVG(CASE WHEN status = "accepted" THEN rating END) as average_rating'),

                    // 3. عد الحالات بناءً على الـ status
                    DB::raw('COUNT(CASE WHEN status = "pending" THEN 1 END) as pending_count'),
                    DB::raw('COUNT(CASE WHEN status = "rejected" THEN 1 END) as rejected_count'),
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
