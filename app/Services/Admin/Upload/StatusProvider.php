<?php

namespace App\Services\Admin\Upload;

use App\Models\Course;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

final class StatusProvider
{
    public function __construct(
        private MetaStore $metaStore,
    ) {}

    public function get(Course $course, string $uploadId): array
    {
        $courseId = $course->id;

        $result = $this->metaStore->withMetaLock($courseId, $uploadId, function (?array $meta) {
            if ($meta === null) {
                return null;
            }

            return $this->buildStatusResponse($meta);
        });

        if ($result === null) {
            throw (new ModelNotFoundException)->setModel('UploadSession', $uploadId);
        }

        return $result;
    }

    private function buildStatusResponse(array $meta): array
    {
        $totalChunks    = (int) $meta['total_chunks'];
        $completedCount = count($meta['completed_chunks'] ?? []);
        $progress       = $totalChunks > 0
            ? (int) round($completedCount / $totalChunks * 100)
            : 0;

        if (in_array($meta['status'], ['uploaded', 'finalizing'], true)) {
            $progress = 100;
        }

        return [
            'meta_version'     => (int) ($meta['meta_version'] ?? config('upload.meta_version')),
            'upload_id'        => $meta['upload_id'],
            'status'           => $meta['status'],
            'total_chunks'     => $totalChunks,
            'completed_count'  => $completedCount,
            'progress'         => $progress,
            'missing_chunks'   => $this->missingChunks($meta),
            'expected_filesize'=> (int) $meta['expected_filesize'],
            'final_file'       => $meta['final_file'] ?? null,
            'expires_at'       => $meta['expires_at'] ?? null,
            'created_at'       => $meta['created_at'] ?? null,
            'updated_at'       => $meta['updated_at'] ?? null,
            'last_error'       => $meta['last_error'] ?? null,
        ];
    }

    private function missingChunks(array $meta): array
    {
        $missing = [];

        for ($i = 0; $i < $meta['total_chunks']; $i++) {
            if (! isset($meta['completed_chunks'][(string) $i])) {
                $missing[] = $i;
            }
        }

        return $missing;
    }
}
