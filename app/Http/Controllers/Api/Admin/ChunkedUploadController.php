<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedUploadController extends Controller
{
    /**
     * Receive one video chunk and, on the final chunk, assemble the full file.
     *
     * POST /api/admin/courses/{course}/previews/chunked-upload
     *
     * Multipart fields:
     *   chunk         – the binary slice (max 2 MB)
     *   chunk_index   – 0-based position of this slice
     *   total_chunks  – how many slices in total
     *   filename      – original file name (used to preserve the extension)
     *   preview_index – which lesson/preview row this video belongs to
     *
     * Returns on intermediate chunks:
     *   { status: "chunk_received", chunk_index: N }
     *
     * Returns on the last chunk:
     *   { status: "complete", video_url, duration_seconds, video_provider, size }
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        $request->validate([
            'chunk'         => ['required', 'file', 'max:2048'],
            'chunk_index'   => ['required', 'integer', 'min:0'],
            'total_chunks'  => ['required', 'integer', 'min:1'],
            'filename'      => ['required', 'string', 'max:255'],
            'preview_index' => ['required', 'integer', 'min:0'],
        ]);

        $chunkIndex   = (int) $request->input('chunk_index');
        $totalChunks  = (int) $request->input('total_chunks');
        $filename     = basename($request->input('filename'));
        $previewIndex = (int) $request->input('preview_index');
        $courseId     = $course->id;

        // Temp storage key: chunks/{courseId}/{previewIndex}/chunk_{i}
        $tempDir  = "chunks/{$courseId}/{$previewIndex}";
        $chunkKey = "{$tempDir}/chunk_{$chunkIndex}";

        Storage::put($chunkKey, $request->file('chunk')->getContent());

        // Not the last chunk – acknowledge and wait for the rest
        if ($chunkIndex < $totalChunks - 1) {
            return response()->json([
                'status'      => 'chunk_received',
                'chunk_index' => $chunkIndex,
            ]);
        }

        // ── All chunks received: assemble ────────────────────────────────────
        $extension   = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'mp4';
        $finalName   = time() . '_' . Str::random(6) . '.' . $extension;
        $finalFolder = 'courses/previews';
        $finalPath   = "{$finalFolder}/{$finalName}";

        // Ensure destination directory exists on the public disk
        $finalFullPath = Storage::disk('public')->path($finalPath);
        $finalDir      = dirname($finalFullPath);

        if (! is_dir($finalDir)) {
            mkdir($finalDir, 0755, true);
        }

        $outputStream = fopen($finalFullPath, 'wb');
        if (! $outputStream) {
            return response()->json(['error' => 'Could not create output file.'], 500);
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFilePath = Storage::path("{$tempDir}/chunk_{$i}");

            if (! file_exists($chunkFilePath)) {
                fclose($outputStream);
                @unlink($finalFullPath);
                return response()->json(['error' => "Missing chunk {$i}. Re-upload required."], 422);
            }

            $inputStream = fopen($chunkFilePath, 'rb');
            stream_copy_to_stream($inputStream, $outputStream);
            fclose($inputStream);
            unlink($chunkFilePath);
        }

        fclose($outputStream);

        $size = (int) filesize($finalFullPath);

        // Extract duration with getID3 (mirrors HandleVideoUploadTrait logic)
        $duration = null;

        try {
            $fileSizeMB = $size / (1024 * 1024);

            if ($fileSizeMB < 20) {
                $id3      = new \getID3;
                $fileInfo = $id3->analyze($finalFullPath);

                if (! empty($fileInfo['playtime_seconds'])) {
                    $duration = (int) round($fileInfo['playtime_seconds']);
                }
                if (! empty($fileInfo['filesize'])) {
                    $size = (int) $fileInfo['filesize'];
                }
            }
        } catch (\Throwable) {
            // Duration stays null; the front-end HTML5 fallback covers this
        }

        // Clean up the now-empty temp directory for this preview
        Storage::deleteDirectory($tempDir);

        // Opportunistically remove stale chunk sessions for this course (> 24 h)
        $this->cleanupOldChunkSessions($courseId);

        return response()->json([
            'status'           => 'complete',
            'video_url'        => $finalPath,
            'duration_seconds' => $duration,
            'video_provider'   => 'upload',
            'size'             => $size,
        ]);
    }

    /**
     * Delete any preview-level temp folders older than 24 hours for the given course.
     */
    private function cleanupOldChunkSessions(int $courseId): void
    {
        $courseChunksDir = storage_path("app/chunks/{$courseId}");

        if (! is_dir($courseChunksDir)) {
            return;
        }

        $cutoff = time() - 86400;

        foreach (scandir($courseChunksDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "{$courseChunksDir}/{$entry}";
            if (is_dir($path) && filemtime($path) < $cutoff) {
                Storage::deleteDirectory("chunks/{$courseId}/{$entry}");
            }
        }
    }
}
