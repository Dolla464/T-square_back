<?php

namespace App\Observers;

use App\Events\CoursePurchased;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    private const CACHE_KEYS = [
        'admin_dashboard_all_time_payment_stats',
        'admin_dashboard_overview_stats',
    ];

    /**
     * Handle the Order "saved" event (create + update).
     *
     * Always busts the stats caches so the orders page and overview
     * reflect changes immediately on the next request.
     *
     * Dispatches CoursePurchased when:
     *  - the order was just created with status = completed, OR
     *  - the order's status transitioned to completed from another value.
     */
    public function saved(Order $order): void
    {
        $this->bustStatsCaches();

        if ($order->status !== 'completed') {
            return;
        }

        // New record created directly as completed (e.g. admin manual order)
        $wasJustCreatedAsCompleted = $order->wasRecentlyCreated;

        // Existing record whose status just moved to completed
        $statusTransitionedToCompleted = ! $order->wasRecentlyCreated
            && $order->wasChanged('status')
            && $order->getOriginal('status') !== 'completed';

        if (! $wasJustCreatedAsCompleted && ! $statusTransitionedToCompleted) {
            return;
        }

        $orderId = $order->id;

        DB::afterCommit(function () use ($orderId) {
            $fresh = Order::query()->find($orderId);

            if (! $fresh || $fresh->status !== 'completed') {
                return;
            }

            $this->dispatchCoursePurchased($fresh);
        });
    }

    /**
     * Bust caches when an order is deleted so counts drop immediately.
     */
    public function deleted(Order $order): void
    {
        $this->bustStatsCaches();
    }

    private function bustStatsCaches(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }

    private function dispatchCoursePurchased(Order $order): void
    {
        $enrollments = $order->enrollments()->with(['course', 'student'])->get();

        foreach ($enrollments as $enrollment) {
            event(new CoursePurchased($enrollment));
        }
    }
}
