<?php

namespace App\Jobs;

use App\Services\ActivityLog\ActivityLogWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class LogActivityJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {}

    public function handle(ActivityLogWriter $writer): void
    {
        $writer->write($this->payload);
    }
}
