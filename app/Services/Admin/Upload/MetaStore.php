<?php

namespace App\Services\Admin\Upload;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Sole gateway for meta.json read/write. All other components must use withMetaLock().
 */
final class MetaStore
{
    private const META_FILENAME = 'meta.json';

    public function sessionPath(int $courseId, string $uploadId): string
    {
        $base = rtrim(config('upload.chunks_path'), '/');

        return "{$base}/{$courseId}/{$uploadId}";
    }

    public function sessionFullPath(int $courseId, string $uploadId): string
    {
        return Storage::disk(config('upload.chunks_disk'))->path($this->sessionPath($courseId, $uploadId));
    }

    public function withMetaLock(int $courseId, string $uploadId, callable $callback): mixed
    {
        $tempDir  = $this->sessionFullPath($courseId, $uploadId);
        $metaPath = "{$tempDir}/" . self::META_FILENAME;

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $fp = fopen($metaPath, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Could not open meta.json for locking.');
        }

        flock($fp, LOCK_EX);

        try {
            $meta = $this->readMeta($fp);

            if ($meta !== null) {
                $meta = $this->migrateMetaIfNeeded($meta);
            }

            $result = $callback($meta, $fp, $tempDir);

            return $result;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function migrateMetaIfNeeded(array $meta): array
    {
        $version = (int) ($meta['meta_version'] ?? 0);

        if ($version < 1) {
            $meta['meta_version'] = config('upload.meta_version');
        }

        return $meta;
    }

    public function writeMeta($fp, array $meta): void
    {
        $meta['meta_version'] = config('upload.meta_version');
        $meta['updated_at']   = Carbon::now()->toIso8601String();

        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
    }

    public function deleteSession(int $courseId, string $uploadId): void
    {
        Storage::disk(config('upload.chunks_disk'))->deleteDirectory($this->sessionPath($courseId, $uploadId));
    }

    public function listCourseUploadIds(int $courseId): array
    {
        $disk = Storage::disk(config('upload.chunks_disk'));
        $path = rtrim(config('upload.chunks_path'), '/') . "/{$courseId}";

        if (! $disk->exists($path)) {
            return [];
        }

        return array_values(array_filter($disk->directories($path), fn ($dir) => basename($dir) !== ''));
    }

    public function listAllCourseIds(): array
    {
        $disk = Storage::disk(config('upload.chunks_disk'));
        $path = rtrim(config('upload.chunks_path'), '/');

        if (! $disk->exists($path)) {
            return [];
        }

        return $disk->directories($path);
    }

    /**
     * @param  resource  $fp
     */
    private function readMeta($fp): ?array
    {
        rewind($fp);
        $contents = stream_get_contents($fp);

        if ($contents === false || trim($contents) === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }
}
