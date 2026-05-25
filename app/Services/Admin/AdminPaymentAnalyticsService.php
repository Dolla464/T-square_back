<?php

namespace App\Services\Admin;


use App\Models\Order;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Cache;

class AdminPaymentAnalyticsService
{
    /**
     * جلب وتكييش إحصائيات آخر شهر فقط
     */
    public function getRecentStats(): array
    {
        $cacheKey = 'admin_dashboard_recent_stats';

        return Cache::remember($cacheKey, now()->addHour(), function () {
            $oneMonthAgo = now()->subMonth();

            // 1. Total Orders
            $totalOrders = Order::query()
                ->where('created_at', '>=', $oneMonthAgo)
                ->count();

            // 2. Pending Orders
            $pendingOrders = Order::query()
                ->where('status', 'pending')
                ->where('created_at', '>=', $oneMonthAgo)
                ->count();

            // 3. Refunded Orders
            $refundedOrders = Order::query()
                ->where('status', 'refunded')
                ->where('created_at', '>=', $oneMonthAgo)
                ->count();

            // 4. Total Revenue (باستخدام query وبترتيب يريح الـ Extensions)
            $totalRevenue = Enrollment::query()
                ->join('courses', 'enrollments.course_id', '=', 'courses.id')
                ->where('enrollments.is_completed', true)
                ->where('enrollments.created_at', '>=', $oneMonthAgo)
                ->sum('courses.price');

            return [
                'total_revenue'  => (float) $totalRevenue,
                'total_orders'   => $totalOrders,
                'pending_count'  => $pendingOrders,
                'refunded_count' => $refundedOrders,
            ];
        });
    }
}
