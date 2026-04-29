<?php

namespace App\Listeners;

use App\Events\CoursePurchased;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateCourseFinancialStats implements ShouldQueue
{
    use InteractsWithQueue;
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CoursePurchased $event): void
    {
        $course = $event->enrollment->course;

        $course->increment('total_students');
        $course->increment('total_revenue', $event->enrollment->price_paid);
    }
}
