<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Trait to handle video uploads using the getID3 library.
 */
trait HandleVideoUploadTrait
{
    /**
     * Upload a video file to the public disk and extract its metadata.
     *
     * Returns an array with:
     *   - path     : the stored path relative to the public disk
     *   - duration : duration in seconds (int), or null if undetectable
     *   - size     : file size in bytes (int)
     *
     * @param  string  $folder  Storage sub-folder (e.g. 'courses/previews')
     * @param  string|null  $oldPath  Existing file path to delete before saving the new one
     * @return array{path: string, duration: int|null, size: int}
     */
    public function uploadVideo(UploadedFile $file, string $folder, ?string $oldPath = null): array
    {
        // Remove old video if present
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // Guard: reject invalid uploads early with a descriptive message.
        // An invalid file usually means PHP's upload_max_filesize was exceeded
        // or the temp directory is not configured.
        if (! $file->isValid()) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'The video exceeds the server upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE => 'The video exceeds the form MAX_FILE_SIZE limit.',
                UPLOAD_ERR_PARTIAL => 'The video was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No video file was received.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory is missing (check PHP upload_tmp_dir).',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write the video temp file to disk.',
            ];
            $message = $errors[$file->getError()] ?? 'Video upload failed (error code '.$file->getError().').';
            throw new \RuntimeException($message);
        }

        // Unique filename preserving the original extension
        $extension = $file->getClientOriginalExtension() ?: 'mp4';
        $generatedName = time().'_'.Str::random(6).'.'.$extension;
        $storagePath = "{$folder}/{$generatedName}";

        // Capture the original size before writing (getSize() reads from $_FILES metadata,
        // not from the temp file, so it works even when getRealPath() fails on Windows/Herd).
        $size = $file->getSize() ?: 0;

        // Use getContent() + put() instead of putFileAs() / storeAs().
        // Both of those internally call $file->getRealPath() which returns false on
        // Windows+Herd (realpath() fails on the PHP temp path), then fopen(false)
        // resolves to the CWD (the public/ folder) and crashes.
        // getContent() uses getPathname() — the raw temp path — which works correctly.
        Storage::disk('public')->put($storagePath, $file->getContent());

        // Analyse the already-stored file with getID3 using its absolute disk path.
        // This avoids touching the (possibly gone) temp file entirely.
        $duration = null;
        $absolutePath = Storage::disk('public')->path($storagePath);

        try {
            // Analyse getID3 only for small files (< 20MB) to avoid Timeout
            $fileSizeMB = $size / (1024 * 1024);
            
            if ($fileSizeMB < 20) {
                $id3 = new \getID3;
                $fileInfo = $id3->analyze($absolutePath);

                if (! empty($fileInfo['playtime_seconds'])) {
                    $duration = (int) round($fileInfo['playtime_seconds']);
                }

                if (! empty($fileInfo['filesize'])) {
                    $size = (int) $fileInfo['filesize'];
                }
            }
            // For large files (> 20MB), we rely on the duration sent from the frontend
        } catch (\Throwable $e) {
            // getID3 analysis failed — duration stays null, size keeps the PHP value
            
        }

        return [
            'path' => $storagePath,
            'duration' => $duration,
            'size' => $size,
        ];
    }
}
