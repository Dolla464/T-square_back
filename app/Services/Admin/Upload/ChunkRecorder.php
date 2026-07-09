<?php

namespace App\Services\Admin\Upload;

use App\Models\Course;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class ChunkRecorder
{
    public function __construct(
        private MetaStore $metaStore,
        private UploadStateMachine $stateMachine,
    ) {}

    public function record(Course $course, array $payload, UploadedFile $chunk): array
    {
        $courseId     = $course->id;
        $uploadId     = $payload['upload_id'];
        $chunkIndex   = (int) $payload['chunk_index'];
        $totalChunks  = (int) $payload['total_chunks'];
        $filename     = basename($payload['original_filename']);
        $ext          = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($ext, config('upload.allowed_extensions'), true)) {
            throw ValidationException::withMessages([
                'original_filename' => ['Invalid video file type.'],
            ]);
        }

        if (! $chunk->isValid()) {
            throw ValidationException::withMessages([
                'chunk' => ['Chunk upload failed or was incomplete.'],
            ]);
        }

        $relativeDir = $this->metaStore->sessionPath($courseId, $uploadId);
        $chunkName     = "chunk_{$chunkIndex}";

        Storage::disk(config('upload.chunks_disk'))->putFileAs(
            $relativeDir,
            $chunk,
            $chunkName,
        );

        $chunkFullPath = Storage::disk(config('upload.chunks_disk'))->path("{$relativeDir}/{$chunkName}");
        $checksum      = hash_file('sha256', $chunkFullPath) ?: '';
        $chunkSize     = (int) filesize($chunkFullPath);

        return $this->metaStore->withMetaLock($courseId, $uploadId, function (?array $meta, $fp, string $tempDir) use (
            $courseId,
            $uploadId,
            $chunkIndex,
            $totalChunks,
            $filename,
            $ext,
            $payload,
            $checksum,
            $chunkSize,
        ) {
            $now = Carbon::now()->toIso8601String();

            if ($meta === null) {
                $meta = $this->bootstrapMeta($courseId, $uploadId, $payload, $filename, $ext, $now);
            } else {
                $this->validateConsistency($meta, $payload, $filename, $ext);
            }

            if ($meta['status'] === 'created') {
                $this->stateMachine->transition($meta, 'uploading');
            } elseif ($meta['status'] === 'failed') {
                $meta['last_error'] = null;
                $this->stateMachine->transition($meta, 'uploading');
            } elseif ($meta['status'] !== 'uploading') {
                throw ValidationException::withMessages([
                    'upload_id' => ["Cannot accept chunks while session status is {$meta['status']}."],
                ]);
            }

            $meta['completed_chunks'][(string) $chunkIndex] = [
                'size'        => $chunkSize,
                'checksum'    => $checksum,
                'received_at' => $now,
            ];

            $completedCount = count($meta['completed_chunks']);

            if ($completedCount >= $meta['total_chunks']) {
                $this->assertAllChunksPresent($meta);
                if ($meta['status'] === 'uploading') {
                    $this->stateMachine->transition($meta, 'uploaded');
                }
            } elseif ($meta['status'] === 'uploading') {
                // remain uploading
            }

            $this->metaStore->writeMeta($fp, $meta);

            return $this->buildChunkResponse($meta, $chunkIndex);
        });
    }

    private function bootstrapMeta(
        int $courseId,
        string $uploadId,
        array $payload,
        string $filename,
        string $ext,
        string $now,
    ): array {
        $created = Carbon::parse($now);

        return [
            'meta_version'      => config('upload.meta_version'),
            'upload_id'         => $uploadId,
            'course_id'         => $courseId,
            'status'            => 'created',
            'original_filename' => $filename,
            'extension'         => $ext,
            'expected_filesize' => (int) $payload['expected_filesize'],
            'sha256'            => strtolower($payload['sha256']),
            'total_chunks'      => (int) $payload['total_chunks'],
            'chunk_size'        => (int) ($payload['chunk_size'] ?? config('upload.chunk_size_bytes')),
            'completed_chunks'  => [],
            'preview_index'     => isset($payload['preview_index']) ? (int) $payload['preview_index'] : null,
            'final_file'        => null,
            'last_error'        => null,
            'created_at'        => $now,
            'updated_at'        => $now,
            'expires_at'        => $created->copy()->addHours((int) config('upload.session_ttl_hours'))->toIso8601String(),
        ];
    }

    private function validateConsistency(array $meta, array $payload, string $filename, string $ext): void
    {
        $mismatches = [];

        if ($meta['upload_id'] !== $payload['upload_id']) {
            $mismatches[] = 'upload_id';
        }
        if ((int) $meta['total_chunks'] !== (int) $payload['total_chunks']) {
            $mismatches[] = 'total_chunks';
        }
        if ((int) $meta['expected_filesize'] !== (int) $payload['expected_filesize']) {
            $mismatches[] = 'expected_filesize';
        }
        if (strtolower($meta['sha256']) !== strtolower($payload['sha256'])) {
            $mismatches[] = 'sha256';
        }
        if ($meta['original_filename'] !== $filename || $meta['extension'] !== $ext) {
            $mismatches[] = 'original_filename';
        }

        if ($mismatches !== []) {
            throw ValidationException::withMessages([
                'upload_id' => ['Session metadata mismatch: ' . implode(', ', $mismatches)],
            ]);
        }
    }

    private function assertAllChunksPresent(array $meta): void
    {
        for ($i = 0; $i < $meta['total_chunks']; $i++) {
            if (! isset($meta['completed_chunks'][(string) $i])) {
                throw ValidationException::withMessages([
                    'chunk' => ["Missing chunk index {$i}."],
                ]);
            }
        }
    }

    private function buildChunkResponse(array $meta, int $chunkIndex): array
    {
        $totalChunks    = (int) $meta['total_chunks'];
        $completedCount = count($meta['completed_chunks']);

        return [
            'status'          => 'chunk_received',
            'upload_id'       => $meta['upload_id'],
            'chunk_index'     => $chunkIndex,
            'completed_count' => $completedCount,
            'total_chunks'    => $totalChunks,
            'session_status'  => $meta['status'],
            'progress'        => $totalChunks > 0 ? (int) round($completedCount / $totalChunks * 100) : 0,
        ];
    }
}
