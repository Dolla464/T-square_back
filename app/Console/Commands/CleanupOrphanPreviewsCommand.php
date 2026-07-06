<?php

namespace App\Console\Commands;

use App\Models\CoursePreview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanPreviewsCommand extends Command
{
    protected $signature   = 'previews:cleanup-orphans';
    protected $description = 'Delete uploaded preview video files that have no matching course_previews DB row (orphans older than 24 h)';

    public function handle(): int
    {
        $folder = 'courses/previews';

        if (! Storage::disk('public')->exists($folder)) {
            $this->info('No courses/previews directory found. Nothing to clean.');
            return self::SUCCESS;
        }

        $files   = Storage::disk('public')->files($folder);
        $cutoff  = now()->subDay()->timestamp;
        $deleted = 0;

        // Fetch all stored paths that are on the local disk (not external URLs).
        // The accessor on CoursePreview converts paths to full URLs, so we query
        // the raw DB value via the query builder.
        $knownPaths = CoursePreview::withTrashed()
            ->whereNotNull('video_url')
            ->where('video_provider', 'upload')
            ->pluck('video_url')
            ->map(fn ($v) => ltrim(parse_url($v, PHP_URL_PATH) ?? $v, '/storage/'))
            ->flip()
            ->all();

        foreach ($files as $filePath) {
            // Skip files modified within the last 24 h (may still be in-flight)
            $lastModified = Storage::disk('public')->lastModified($filePath);
            if ($lastModified >= $cutoff) {
                continue;
            }

            if (! array_key_exists($filePath, $knownPaths)) {
                Storage::disk('public')->delete($filePath);
                $deleted++;
                $this->line("  Deleted orphan: {$filePath}");
            }
        }

        $this->info("Done. Removed {$deleted} orphan preview file(s).");
        return self::SUCCESS;
    }
}
