<?php

namespace App\Jobs;

use App\Models\Enrollment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEnrollmentCompleted implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Enrollment $enrollment) {}

    public function handle(): void
    {
        $this->enrollment->updateQuietly([
            'completed_at' => now(),
        ]);

        Log::info("Job Processed: Enrollment #{$this->enrollment->id} marked as completed");
    }
}
