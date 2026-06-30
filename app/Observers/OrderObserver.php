<?php

namespace App\Observers;

use App\Events\CoursePurchased;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    /**
     * Handle the Order "saved" event (create + update).
     * wasChanged() is valid here — saved runs after persistence.
     * Dispatches CoursePurchased only once when status transitions to completed,
     * after the surrounding DB transaction commits.
     */
    public function saved(Order $order): void
    {
        if ($order->status !== 'completed') {
            return;
        }

        if (! $order->wasChanged('status')) {
            return;
        }

        if ($order->getOriginal('status') === 'completed') {
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

    private function dispatchCoursePurchased(Order $order): void
    {
        $enrollments = $order->enrollments()->with(['course', 'student'])->get();

        foreach ($enrollments as $enrollment) {
            event(new CoursePurchased($enrollment));
        }
    }
}
