<?php

namespace App\Services\Admin;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function __construct(
        private readonly AdminPaymentAnalyticsService $paymentAnalytics,
    ) {}

    public function getStats(): array
    {
        return Cache::remember('admin_dashboard_overview_stats', now()->addHour(), function () {
            $paymentStats = $this->paymentAnalytics->getRecentStats();

            return [
                'total_revenue'   => $paymentStats['total_revenue'],
                'total_students'  => Student::count(),
                'total_courses'   => Course::count(),
                'active_courses'  => Course::where('status', 'published')->count(),
                'total_orders'    => $paymentStats['total_orders'],
                'pending_count'   => $paymentStats['pending_count'],
                'refunded_count'  => $paymentStats['refunded_count'],
            ];
        });
    }

    public function getRevenueChart(string $period = 'month'): array
    {
        $period = in_array($period, ['week', 'month', 'year'], true) ? $period : 'month';

        return match ($period) {
            'week'  => $this->buildDailyRevenueChart(7),
            'month' => $this->buildMonthlyRevenueChart(7),
            'year'  => $this->buildYearlyRevenueChart(6),
        };
    }

    public function getCourseSales(string $period = 'month', int $limit = 5): array
    {
        $period = in_array($period, ['week', 'month', 'year'], true) ? $period : 'month';
        [$from, $to] = $this->resolvePeriodRange($period);

        $courses = Course::query()
            ->select(['courses.id', 'courses.title'])
            ->selectSub(
                Enrollment::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('enrollments.course_id', 'courses.id')
                    ->whereBetween('enrollments.created_at', [$from, $to]),
                'sales_count'
            )
            ->orderByDesc('sales_count')
            ->limit($limit)
            ->get();

        return [
            'labels' => $courses->pluck('title')->all(),
            'data'   => $courses->pluck('sales_count')->map(fn ($count) => (int) $count)->all(),
        ];
    }

    public function getRecentEnrollments(int $limit = 4): array
    {
        return Enrollment::query()
            ->with([
                'student:id,full_name,avatar',
                'course:id,title',
            ])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Enrollment $enrollment) => [
                'id'           => $enrollment->id,
                'student_name' => $enrollment->student?->full_name,
                'course_title' => $enrollment->course?->title,
                'created_at'   => $enrollment->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    public function getRecentOrders(int $limit = 4): array
    {
        return Order::query()
            ->with([
                'student:id,full_name',
                'enrollments.course:id,title',
            ])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Order $order) {
                $courseTitle = $order->enrollments->first()?->course?->title;

                return [
                    'id'            => $order->id,
                    'student_name'  => $order->student?->full_name ?? $order->billing_name,
                    'course_title'  => $courseTitle,
                    'status'        => $order->status,
                    'total_amount'  => (float) $order->total_amount,
                    'created_at'    => $order->created_at?->toISOString(),
                ];
            })
            ->values()
            ->all();
    }

    public function getTopCourses(int $limit = 3): array
    {
        return Course::query()
            ->select([
                'id',
                'title',
                'avg_rating',
                'total_students',
                'total_revenue',
            ])
            ->orderByDesc(DB::raw('COALESCE(total_revenue, 0)'))
            ->orderByDesc(DB::raw('COALESCE(total_students, 0)'))
            ->limit($limit)
            ->get()
            ->map(fn (Course $course) => [
                'id'             => $course->id,
                'title'          => $course->title,
                'students_count' => (int) ($course->total_students ?? 0),
                'rating'         => round((float) ($course->avg_rating ?? 0), 1),
                'revenue'        => (float) ($course->total_revenue ?? 0),
            ])
            ->values()
            ->all();
    }

    private function buildDailyRevenueChart(int $days): array
    {
        $labels = [];
        $data   = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date     = Carbon::now()->subDays($i);
            $labels[] = $date->format('Y-m-d');
            $data[]   = $this->sumCompletedRevenue(
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay()
            );
        }

        return compact('labels', 'data');
    }

    private function buildMonthlyRevenueChart(int $months): array
    {
        $labels = [];
        $data   = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date     = Carbon::now()->subMonths($i);
            $labels[] = $date->format('Y-m');
            $data[]   = $this->sumCompletedRevenue(
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth()
            );
        }

        return compact('labels', 'data');
    }

    private function buildYearlyRevenueChart(int $years): array
    {
        $labels = [];
        $data   = [];

        for ($i = $years - 1; $i >= 0; $i--) {
            $date     = Carbon::now()->subYears($i);
            $labels[] = (string) $date->year;
            $data[]   = $this->sumCompletedRevenue(
                $date->copy()->startOfYear(),
                $date->copy()->endOfYear()
            );
        }

        return compact('labels', 'data');
    }

    private function sumCompletedRevenue(Carbon $from, Carbon $to): float
    {
        return (float) Order::query()
            ->where('status', 'completed')
            ->where('total_amount', '>', 0)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_amount');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriodRange(string $period): array
    {
        $to = Carbon::now()->endOfDay();

        $from = match ($period) {
            'week'  => Carbon::now()->subDays(6)->startOfDay(),
            'month' => Carbon::now()->subMonths(6)->startOfMonth(),
            'year'  => Carbon::now()->subYears(5)->startOfYear(),
        };

        return [$from, $to];
    }
}
