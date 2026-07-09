<?php

namespace App\Services\Admin\Upload;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

final class CleanupManager
{
    public function __construct(
        private MetaStore $metaStore,
        private UploadStateMachine $stateMachine,
    ) {}

    public function run(): int
    {
        $deleted = 0;
        $disk    = Storage::disk(config('upload.chunks_disk'));
        $root    = rtrim(config('upload.chunks_path'), '/');

        if (! $disk->exists($root)) {
            return 0;
        }

        foreach ($disk->directories($root) as $courseDir) {
            $courseId = (int) basename($courseDir);

            foreach ($disk->directories($courseDir) as $uploadDir) {
                $uploadId = basename($uploadDir);
                $metaPath = "{$uploadDir}/meta.json";

                if (! $disk->exists($metaPath)) {
                    $disk->deleteDirectory($uploadDir);
                    $deleted++;

                    continue;
                }

                if ($this->cleanupSession($courseId, $uploadId)) {
                    $deleted++;
                }
            }

            if (count($disk->directories($courseDir)) === 0 && count($disk->files($courseDir)) === 0) {
                $disk->deleteDirectory($courseDir);
            }
        }

        return $deleted;
    }

    private function cleanupSession(int $courseId, string $uploadId): bool
    {
        $shouldDelete = false;

        $this->metaStore->withMetaLock($courseId, $uploadId, function (?array $meta, $fp) use (&$shouldDelete) {
            if ($meta === null) {
                $shouldDelete = true;

                return null;
            }

            $now = Carbon::now();

            if ($meta['status'] === 'complete') {
                $shouldDelete = true;

                return null;
            }

            if ($meta['status'] === 'finalizing') {
                $timeout = (int) config('upload.finalizing_timeout_minutes');
                $updated = Carbon::parse($meta['updated_at'] ?? $meta['created_at']);

                if ($updated->lt($now->copy()->subMinutes($timeout))) {
                    $this->stateMachine->transition($meta, 'failed');
                    $meta['last_error'] = 'Finalize timed out';
                    $this->metaStore->writeMeta($fp, $meta);
                }

                return null;
            }

            $expiresAt = isset($meta['expires_at'])
                ? Carbon::parse($meta['expires_at'])
                : Carbon::parse($meta['created_at'])->addHours((int) config('upload.session_ttl_hours'));

            if ($expiresAt->lt($now) && in_array($meta['status'], ['created', 'uploading', 'uploaded', 'failed'], true)) {
                $shouldDelete = true;
            }

            return null;
        });

        if ($shouldDelete) {
            $this->metaStore->deleteSession($courseId, $uploadId);

            return true;
        }

        return false;
    }
}
