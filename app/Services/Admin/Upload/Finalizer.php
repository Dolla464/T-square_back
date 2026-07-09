<?php

namespace App\Services\Admin\Upload;

use App\Exceptions\UploadAlreadyFinalizingException;
use App\Models\Course;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Finalizer
{
    public function __construct(
        private MetaStore $metaStore,
        private UploadStateMachine $stateMachine,
    ) {}

    public function finalize(Course $course, string $uploadId, ?int $durationSeconds): array
    {
        $courseId = $course->id;

        $prepared = $this->metaStore->withMetaLock($courseId, $uploadId, function (?array $meta, $fp) {
            if ($meta === null) {
                throw ValidationException::withMessages([
                    'upload_id' => ['Upload session not found.'],
                ]);
            }

            if (! in_array($meta['status'], ['uploaded', 'failed'], true)) {
                throw ValidationException::withMessages([
                    'upload_id' => ["Cannot finalize while session status is {$meta['status']}."],
                ]);
            }

            if ($meta['status'] === 'failed') {
                $this->stateMachine->transition($meta, 'uploaded');
            }

            $this->stateMachine->transition($meta, 'finalizing');
            $meta['last_error'] = null;
            $this->metaStore->writeMeta($fp, $meta);

            return $meta;
        });

        $tempDir  = $this->metaStore->sessionFullPath($courseId, $uploadId);
        $lockPath = "{$tempDir}/.finalize.lock";
        $lockFp   = fopen($lockPath, 'c');

        if ($lockFp === false) {
            $this->markFailed($courseId, $uploadId, 'Could not acquire finalize lock.');

            throw ValidationException::withMessages([
                'upload_id' => ['Could not start finalization.'],
            ]);
        }

        if (! flock($lockFp, LOCK_EX | LOCK_NB)) {
            fclose($lockFp);

            throw new UploadAlreadyFinalizingException('Already finalizing.');
        }

        $assembledTemp = null;

        try {
            $assembledTemp = $this->assembleChunks($prepared, $tempDir);
            $this->verifyAssembledFile($assembledTemp, $prepared);

            $finalRelative = $this->moveToFinalStorage($assembledTemp, $prepared['extension']);
            $size          = (int) filesize(Storage::disk(config('upload.final_disk'))->path($finalRelative));
            $duration      = $this->resolveDuration($assembledTemp, $durationSeconds, $size);

            $response = [
                'status'           => 'complete',
                'video_url'        => $finalRelative,
                'final_file'       => $finalRelative,
                'duration_seconds' => $duration,
                'video_provider'   => 'upload',
                'size'             => $size,
                'sha256'           => strtolower($prepared['sha256']),
            ];

            flock($lockFp, LOCK_UN);
            fclose($lockFp);

            $this->metaStore->deleteSession($courseId, $uploadId);

            return $response;
        } catch (\Throwable $e) {
            if ($assembledTemp && file_exists($assembledTemp)) {
                @unlink($assembledTemp);
            }

            flock($lockFp, LOCK_UN);
            fclose($lockFp);

            $message = $e instanceof ValidationException
                ? collect($e->errors())->flatten()->first()
                : $e->getMessage();

            $this->markFailed($courseId, $uploadId, (string) $message);

            throw $e;
        }
    }

    private function assembleChunks(array $meta, string $tempDir): string
    {
        $assembledTemp = "{$tempDir}/assembled.tmp";
        $output        = fopen($assembledTemp, 'wb');

        if ($output === false) {
            throw new \RuntimeException('Could not create assembled temp file.');
        }

        for ($i = 0; $i < $meta['total_chunks']; $i++) {
            $chunkPath = "{$tempDir}/chunk_{$i}";

            if (! file_exists($chunkPath)) {
                fclose($output);
                @unlink($assembledTemp);

                throw ValidationException::withMessages([
                    'upload_id' => ["Missing chunk {$i}. Please re-upload."],
                ]);
            }

            $input = fopen($chunkPath, 'rb');
            stream_copy_to_stream($input, $output);
            fclose($input);
        }

        fclose($output);

        return $assembledTemp;
    }

    private function verifyAssembledFile(string $assembledTemp, array $meta): void
    {
        $size = (int) filesize($assembledTemp);

        if ($size !== (int) $meta['expected_filesize']) {
            throw ValidationException::withMessages([
                'upload_id' => ['Assembled file size does not match expected.'],
            ]);
        }

        $mime = mime_content_type($assembledTemp) ?: '';

        if (! str_starts_with($mime, 'video/')) {
            throw ValidationException::withMessages([
                'upload_id' => ['Assembled file is not a valid video.'],
            ]);
        }

        $computed = hash_file('sha256', $assembledTemp) ?: '';

        if (strtolower($computed) !== strtolower($meta['sha256'])) {
            throw ValidationException::withMessages([
                'upload_id' => ['Checksum mismatch.'],
            ]);
        }
    }

    private function moveToFinalStorage(string $assembledTemp, string $ext): string
    {
        $finalName     = time() . '_' . Str::random(6) . '.' . $ext;
        $finalRelative = rtrim(config('upload.previews_path'), '/') . '/' . $finalName;
        $finalFull     = Storage::disk(config('upload.final_disk'))->path($finalRelative);
        $finalDir      = dirname($finalFull);

        if (! is_dir($finalDir)) {
            mkdir($finalDir, 0755, true);
        }

        if (! rename($assembledTemp, $finalFull)) {
            throw new \RuntimeException('Could not move assembled file to final storage.');
        }

        return $finalRelative;
    }

    private function resolveDuration(string $assembledTemp, ?int $durationSeconds, int $size): ?int
    {
        if ($durationSeconds !== null && $durationSeconds > 0) {
            return $durationSeconds;
        }

        $maxMb = (float) config('upload.getid3_max_size_mb');

        if ($size / (1024 * 1024) >= $maxMb) {
            return null;
        }

        try {
            $id3      = new \getID3;
            $fileInfo = $id3->analyze($assembledTemp);

            if (! empty($fileInfo['playtime_seconds'])) {
                return (int) round($fileInfo['playtime_seconds']);
            }
        } catch (\Throwable) {
            // fall through
        }

        return null;
    }

    private function markFailed(int $courseId, string $uploadId, string $error): void
    {
        try {
            $this->metaStore->withMetaLock($courseId, $uploadId, function (?array $meta, $fp) use ($error) {
                if ($meta === null) {
                    return null;
                }

                if ($meta['status'] === 'finalizing') {
                    $this->stateMachine->transition($meta, 'failed');
                    $meta['last_error'] = $error;
                    $this->metaStore->writeMeta($fp, $meta);
                }

                return null;
            });
        } catch (\Throwable) {
            // best effort
        }
    }
}
