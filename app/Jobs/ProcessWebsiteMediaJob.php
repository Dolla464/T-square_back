<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessWebsiteMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HandleImageUploadTrait;

    public int $tries   = 3;
    public int $timeout = 300;

    /**
     * @param  array<int, string>  $pendingPaths  Paths on the local disk (pending/website-media/…)
     * @param  array<int, string>  $oldImages     URLs/paths to delete from public disk after success
     * @param  array<int, string>  $baseImages    Existing image URLs to prepend in the merged result
     */
    public function __construct(
        private readonly array $pendingPaths,
        private readonly string $settingsKey,
        private readonly string $folder,
        private readonly int $maxSize,
        private readonly bool $isSingle,
        private readonly array $oldImages,
        private readonly array $baseImages,
    ) {}

    public function handle(): void
    {
        $processedUrls = [];

        foreach ($this->pendingPaths as $pendingPath) {
            $absPath = Storage::disk('local')->path($pendingPath);

            if (! file_exists($absPath)) {
                continue;
            }

            try {
                $processedUrl = $this->processRawImage($absPath, $this->folder, $this->maxSize);
                $processedUrls[] = $processedUrl;
            } catch (\Throwable) {
                // Skip images that fail to process; they'll be cleaned up below
            } finally {
                Storage::disk('local')->delete($pendingPath);
            }
        }

        if (empty($processedUrls)) {
            return;
        }

        // Use a cache lock to prevent concurrent jobs from clobbering the setting
        Cache::lock("website-media-{$this->settingsKey}", 30)->block(10, function () use ($processedUrls) {
            if ($this->isSingle) {
                $finalData = $processedUrls[0];
                Setting::set($this->settingsKey, $finalData, 'string', 'general');
            } else {
                $finalData = array_merge($this->baseImages, $processedUrls);
                Setting::set($this->settingsKey, $finalData, 'json', 'general');
            }
        });

        // Delete old images after the new ones are saved successfully
        if (! empty($this->oldImages)) {
            $this->deleteStorageImages($this->oldImages);
        }
    }

    public function failed(\Throwable $e): void
    {
        // Clean up any leftover pending raw files so they do not accumulate
        foreach ($this->pendingPaths as $pendingPath) {
            Storage::disk('local')->delete($pendingPath);
        }
    }

    /**
     * Load a raw image from an absolute path, resize and convert to WebP,
     * save on the public disk, and return the full public URL.
     */
    private function processRawImage(string $absPath, string $folder, int $maxSize): string
    {
        if (! function_exists('imagewebp')) {
            throw new \RuntimeException('GD extension with WebP support is required.');
        }

        $info = @getimagesize($absPath);
        if ($info === false) {
            throw new \RuntimeException('Could not read image information.');
        }

        $width  = $info[0];
        $height = $info[1];

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($absPath),
            IMAGETYPE_PNG  => imagecreatefrompng($absPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($absPath),
            default        => throw new \RuntimeException('Unsupported image type.'),
        };

        if ($source === false) {
            throw new \RuntimeException('Could not load the image for processing.');
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($source);
        }

        imagealphablending($source, true);
        imagesavealpha($source, true);

        if ($width > $maxSize || $height > $maxSize) {
            if ($width > $height) {
                $newWidth  = $maxSize;
                $newHeight = (int) ($height * ($maxSize / $width));
            } else {
                $newHeight = $maxSize;
                $newWidth  = (int) ($width * ($maxSize / $height));
            }

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        ob_start();
        imagewebp($source, null, 80);
        $webpContent = ob_get_clean();
        imagedestroy($source);

        if ($webpContent === false || $webpContent === '') {
            throw new \RuntimeException('Failed to convert the image to WebP.');
        }

        if (! Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $filename = uniqid() . '_' . Str::random(5) . '.webp';
        $fullPath = "{$folder}/{$filename}";

        if (! Storage::disk('public')->put($fullPath, $webpContent)) {
            throw new \RuntimeException('Failed to save the processed image to disk.');
        }

        return Storage::disk('public')->url($fullPath);
    }
}
