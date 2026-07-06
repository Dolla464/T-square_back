<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @tags Admin: Courses
 */
class ChunkedUploadController extends Controller
{
    /** Allowed video extensions (must match frontend accept list). */
    private const ALLOWED_EXTENSIONS = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];

    /**
     * Receive one video chunk and persist it to temporary storage.
     *
     * POST /api/admin/courses/{course}/previews/chunked-upload
     *
     * Multipart fields:
     *   chunk         – the binary slice (max 5 MB)
     *   chunk_index   – 0-based position of this slice
     *   total_chunks  – total number of slices
     *   filename      – original file name (used to validate the extension)
     *   preview_index – which lesson/preview row this video belongs to
     *
     * Returns:
     *   { status: "chunk_received", chunk_index: N }
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        try {
            $request->validate([
                'chunk'         => ['required', 'file', 'max:5120'],
                'chunk_index'   => ['required', 'integer', 'min:0'],
                'total_chunks'  => ['required', 'integer', 'min:1'],
                'filename'      => ['required', 'string', 'max:255'],
                'preview_index' => ['required', 'integer', 'min:0'],
            ]);

            $uploaded = $request->file('chunk');
            if (! $uploaded || ! $uploaded->isValid()) {
                $code = $uploaded?->getError() ?? UPLOAD_ERR_NO_FILE;

                return response()->json([
                    'error'        => 'Chunk upload failed or was incomplete.',
                    'upload_error' => $code,
                ], 422);
            }

            $filename     = basename($request->input('filename'));
            $chunkIndex   = (int) $request->input('chunk_index');
            $previewIndex = (int) $request->input('preview_index');
            $courseId     = $course->id;

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                return response()->json(['error' => 'Invalid video file type. Allowed: mp4, webm, ogg, mov.'], 422);
            }

            $tempDir = "chunks/{$courseId}/{$previewIndex}";

            // Stream the temp upload to disk — avoids loading the whole chunk into memory.
            Storage::disk('local')->putFileAs(
                $tempDir,
                $uploaded,
                "chunk_{$chunkIndex}",
            );

            return response()->json([
                'status'      => 'chunk_received',
                'chunk_index' => $chunkIndex,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Chunked upload store failed', [
                'course_id'     => $course->id,
                'chunk_index'   => $request->input('chunk_index'),
                'preview_index' => $request->input('preview_index'),
                'message'       => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Failed to store chunk on server.',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal storage error.',
            ], 500);
        }
    }

    /**
     * Assemble previously uploaded chunks into the final video file.
     *
     * POST /api/admin/courses/{course}/previews/finalize-upload
     *
     * JSON / form fields:
     *   filename      – original file name (extension preserved)
     *   total_chunks  – how many chunks were uploaded
     *   preview_index – identifies the temp chunk folder
     *
     * Returns:
     *   { status: "complete", video_url, duration_seconds, video_provider, size }
     */
    public function finalize(Request $request, Course $course): JsonResponse
    {
        $request->validate([
            'filename'      => ['required', 'string', 'max:255'],
            'total_chunks'  => ['required', 'integer', 'min:1'],
            'preview_index' => ['required', 'integer', 'min:0'],
        ]);

        $filename     = basename($request->input('filename'));
        $totalChunks  = (int) $request->input('total_chunks');
        $previewIndex = (int) $request->input('preview_index');
        $courseId     = $course->id;

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'mp4';

        // Guard extension again (belt-and-suspenders)
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return response()->json(['error' => 'Invalid video file type.'], 422);
        }

        $tempDir = "chunks/{$courseId}/{$previewIndex}";

        $finalName    = time() . '_' . Str::random(6) . '.' . $ext;
        $finalFolder  = 'courses/previews';
        $finalPath    = "{$finalFolder}/{$finalName}";
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
            $chunkFilePath = Storage::disk('local')->path("{$tempDir}/chunk_{$i}");

            if (! file_exists($chunkFilePath)) {
                fclose($outputStream);
                @unlink($finalFullPath);
                return response()->json(['error' => "Missing chunk {$i}. Please re-upload."], 422);
            }

            $inputStream = fopen($chunkFilePath, 'rb');
            stream_copy_to_stream($inputStream, $outputStream);
            fclose($inputStream);
            unlink($chunkFilePath);
        }

        fclose($outputStream);

        // Verify the assembled file is actually a video (defence against spoofed extensions)
        $detectedMime = mime_content_type($finalFullPath) ?: '';
        if (! str_starts_with($detectedMime, 'video/')) {
            @unlink($finalFullPath);
            Storage::deleteDirectory($tempDir);
            return response()->json(['error' => 'Assembled file is not a valid video.'], 422);
        }

        $size = (int) filesize($finalFullPath);

        // Extract duration with getID3 for files under 20 MB; larger files rely on
        // the browser-supplied duration (set via HTML5 Video API before upload starts).
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
        $courseChunksDir = Storage::disk('local')->path("chunks/{$courseId}");

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
