<?php

namespace App\Services\Admin;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class AdminPaymentAnalyticsService
{
    /**
     * Get stats for the admin payments dashboard.
     * When date range is provided, stats reflect that period (no cache).
     * Otherwise, returns cached all-time stats.
     */
    public function getRecentStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        if ($dateFrom || $dateTo) {
            return $this->computeStats($dateFrom, $dateTo);
        }

        $cacheKey = 'admin_dashboard_all_time_payment_stats';

        return Cache::remember($cacheKey, now()->addHour(), function () {
            return $this->computeStats(null, null);
        });
    }

    private function computeStats(?string $dateFrom, ?string $dateTo): array
    {
        $baseQuery = Order::query();

        if ($dateFrom) {
            $baseQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $baseQuery->whereDate('created_at', '<=', $dateTo);
        }

        $totalOrders = (clone $baseQuery)->count();

        $pendingOrders = (clone $baseQuery)
            ->where('status', 'pending')
            ->count();

        $refundedOrders = (clone $baseQuery)
            ->where('status', 'refunded')
            ->count();

        $totalRevenue = (clone $baseQuery)
            ->where('status', 'completed')
            ->where('total_amount', '>', 0)
            ->sum('total_amount');

        return [
            'total_revenue'  => (float) $totalRevenue,
            'total_orders'   => $totalOrders,
            'pending_count'  => $pendingOrders,
            'refunded_count' => $refundedOrders,
        ];
    }
}
