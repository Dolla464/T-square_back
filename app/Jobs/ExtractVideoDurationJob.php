<?php

namespace App\Jobs;

use App\Models\CoursePreview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ExtractVideoDurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public readonly int $previewId) {}

    public function handle(): void
    {
        $preview = CoursePreview::find($this->previewId);

        // Nothing to do if the record was deleted or already has a duration
        if (! $preview || $preview->duration_seconds !== null) {
            return;
        }

        // Only process locally-uploaded files (not YouTube/Vimeo/external)
        if ($preview->video_provider !== 'upload') {
            return;
        }

        // Resolve the absolute path from the stored relative path
        // The accessor converts it to a full URL, so we use the raw DB value via getRawOriginal.
        $relativePath = $preview->getRawOriginal('video_url');

        if (! $relativePath || str_starts_with($relativePath, 'http')) {
            return;
        }

        $absolutePath = Storage::disk('public')->path($relativePath);

        if (! file_exists($absolutePath)) {
            return;
        }

        try {
            $id3      = new \getID3;
            $fileInfo = $id3->analyze($absolutePath);

            if (! empty($fileInfo['playtime_seconds'])) {
                $preview->update(['duration_seconds' => (int) round($fileInfo['playtime_seconds'])]);
            }
        } catch (\Throwable) {
            // Fail silently – duration remaining null is acceptable
        }
    }
}
