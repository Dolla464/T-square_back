<?php

namespace App\Services\Admin\Upload;

use App\Models\Course;
use Illuminate\Http\UploadedFile;

final class UploadSessionService
{
    public function __construct(
        private ChunkRecorder $recorder,
        private Finalizer $finalizer,
        private StatusProvider $status,
        private CleanupManager $cleanup,
    ) {}

    public function recordChunk(Course $course, array $payload, UploadedFile $chunk): array
    {
        return $this->recorder->record($course, $payload, $chunk);
    }

    public function finalize(Course $course, string $uploadId, ?int $durationSeconds): array
    {
        return $this->finalizer->finalize($course, $uploadId, $durationSeconds);
    }

    public function getStatus(Course $course, string $uploadId): array
    {
        return $this->status->get($course, $uploadId);
    }

    public function cleanupStaleSessions(): int
    {
        return $this->cleanup->run();
    }
}
