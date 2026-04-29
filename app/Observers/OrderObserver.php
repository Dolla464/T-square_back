<?php

namespace App\Observers;

use App\Models\Order;
use App\Events\CoursePurchased;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // 1. نتأكد إن الحالة اتغيرت لـ completed
        if ($order->wasChanged('status') && $order->status === 'completed') {
            
            // 2. نجيب كل الاشتراكات اللي جوه الطلب ده (لأن ممكن الطلب يكون فيه أكتر من كورس)
            $enrollments = $order->enrollments()->with(['course', 'student'])->get();

            // 3. نلف عليهم ونطلق الحدث لكل كورس
            foreach ($enrollments as $enrollment) {
                // الحدث ده هو اللي هيروح للـ Queue يزود الفلوس، يزود الطلاب، ويبعت الإشعارات!
                event(new CoursePurchased($enrollment));
            }
        }
    }
}