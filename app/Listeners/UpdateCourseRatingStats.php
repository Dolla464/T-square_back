<?php

namespace App\Listeners;

use App\Events\ReviewSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateCourseRatingStats implements ShouldQueue
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
    public function handle(ReviewSubmitted $event): void
    {
        if ($event->review && $event->review->course) {
            $event->review->course->updateRatingStats();
        }
    }
}
