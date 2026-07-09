<?php

namespace App\Console\Commands;

use App\Services\Admin\Upload\UploadSessionService;
use Illuminate\Console\Command;

class CleanupChunksCommand extends Command
{
    protected $signature   = 'chunks:cleanup';
    protected $description = 'Delete stale incomplete upload sessions using meta.json expires_at and status';

    public function handle(UploadSessionService $uploadSession): int
    {
        $deleted = $uploadSession->cleanupStaleSessions();

        $this->info("Done. Cleaned up {$deleted} stale upload session(s).");

        return self::SUCCESS;
    }
}
