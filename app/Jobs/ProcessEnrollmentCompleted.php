<?php

namespace App\Jobs;

use App\Models\Enrollment;
use App\Notifications\CertificateReady;
use App\Services\User\CertificateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEnrollmentCompleted implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Enrollment $enrollment) {}

    /**
     * Execute the job.
     */
    public function handle(CertificateService $certificateService): void
    {
        $this->enrollment->updateQuietly([
            'completed_at' => now()
        ]);

        // Issue the certificate and save it to DB
        $certificateService->issueCertificate($this->enrollment);

        // نبتعت الnotification للطالب
        $student = $this->enrollment->student;
        $student->notify(new CertificateReady($this->enrollment));

        Log::info("Job Processed: Certificate issued and notification sent for Enrollment #{$this->enrollment->id}");
    }
}
