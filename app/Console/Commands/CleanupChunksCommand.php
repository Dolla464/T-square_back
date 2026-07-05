<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupChunksCommand extends Command
{
    protected $signature   = 'chunks:cleanup';
    protected $description = 'Delete temp chunk folders older than 24 hours from storage/app/chunks/';

    public function handle(): int
    {
        $chunksRoot = storage_path('app/chunks');

        if (! is_dir($chunksRoot)) {
            $this->info('No chunks directory found. Nothing to clean.');
            return self::SUCCESS;
        }

        $cutoff  = now()->subDay()->timestamp;
        $deleted = 0;

        foreach (scandir($chunksRoot) as $courseDir) {
            if ($courseDir === '.' || $courseDir === '..') {
                continue;
            }

            $coursePath = "{$chunksRoot}/{$courseDir}";
            if (! is_dir($coursePath)) {
                continue;
            }

            foreach (scandir($coursePath) as $previewDir) {
                if ($previewDir === '.' || $previewDir === '..') {
                    continue;
                }

                $previewPath = "{$coursePath}/{$previewDir}";
                if (! is_dir($previewPath)) {
                    continue;
                }

                if (filemtime($previewPath) < $cutoff) {
                    Storage::deleteDirectory("chunks/{$courseDir}/{$previewDir}");
                    $deleted++;
                    $this->line("  Removed: chunks/{$courseDir}/{$previewDir}");
                }
            }

            // Remove the course-level folder if it is now empty
            if (is_dir($coursePath) && count(scandir($coursePath)) === 2) {
                rmdir($coursePath);
            }
        }

        $this->info("Done. Cleaned up {$deleted} stale chunk folder(s).");
        return self::SUCCESS;
    }
}
