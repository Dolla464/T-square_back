<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\Log;

class VideoActivityLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, array $context = []): void
    {
        $safeContext = collect($context)
            ->except(['access_token', 'refresh_token', 'token', 'google_drive_url', 'webContentLink'])
            ->merge(['timestamp' => now()->toIso8601String()])
            ->all();

        Log::channel(config('logging.default'))->info($event, $safeContext);
    }
}
