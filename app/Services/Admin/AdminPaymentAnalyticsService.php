<?php

namespace App\Services\Admin;


use App\Models\Order;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Cache;

class AdminPaymentAnalyticsService
{
    /**
     * Get and cache the recent stats for the admin dashboard
     */
    public function getRecentStats(): array
    {
        $cacheKey = 'admin_dashboard_recent_stats';

        return Cache::remember($cacheKey, now()->addHour(), function () {
            $oneMonthAgo = now()->subMonth();

            // 1. Total number of orders
            $totalOrders = Order::query()
                ->where('created_at', '>=', $oneMonthAgo)
                ->count();

            // 2. Total number of pending orders
            $pendingOrders = Order::query()
                ->where('status', 'pending')
                ->where('created_at', '>=', $oneMonthAgo)
                ->count();

            // 3. Total number of refunded orders
            $refundedOrders = Order::query()
                ->where('status', 'refunded')
                ->where('created_at', '>=', $oneMonthAgo)
                ->count();

            // 4. Total revenue (using a query and order to avoid extensions)
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
