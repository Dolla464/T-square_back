<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Trait to handle image upload, resizing, and WebP conversion.
 */
trait HandleImageUploadTrait
{
    /**
     * Resolve a storage URL or path to a relative path on the public disk.
     */
    protected function resolveStoragePath(string $urlOrPath): ?string
    {
        if ($urlOrPath === '') {
            return null;
        }

        $storagePublicUrl = Storage::disk('public')->url('');

        if (str_starts_with($urlOrPath, $storagePublicUrl)) {
            return ltrim(substr($urlOrPath, strlen($storagePublicUrl)), '/');
        }

        if (str_starts_with($urlOrPath, '/storage/')) {
            return ltrim(substr($urlOrPath, strlen('/storage/')), '/');
        }

        if (preg_match('#/storage/(.+)$#', $urlOrPath, $matches)) {
            return $matches[1];
        }

        if (! str_starts_with($urlOrPath, 'http://') && ! str_starts_with($urlOrPath, 'https://')) {
            return ltrim($urlOrPath, '/');
        }

        return null;
    }

    /**
     * Delete files from the public disk using URLs or relative paths.
     *
     * @param  array<int, string>  $urlsOrPaths
     */
    protected function deleteStorageImages(array $urlsOrPaths): void
    {
        foreach ($urlsOrPaths as $urlOrPath) {
            if (empty($urlOrPath) || ! is_string($urlOrPath)) {
                continue;
            }

            $relativePath = $this->resolveStoragePath($urlOrPath);

            if ($relativePath && Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        }
    }

    /**
     * Upload multiple images with all-or-nothing rollback on failure.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     *
     * @throws \Exception
     */
    public function uploadImagesBatch(
        array $files,
        string $folder,
        int $maxSize = 800,
        bool $returnUrls = false
    ): array {
        $uploadedPaths = [];

        try {
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    throw new \Exception('Invalid file in upload batch.');
                }

                $uploadedPaths[] = $this->uploadImage($file, $folder, null, $maxSize, $returnUrls);
                \gc_collect_cycles();
            }
        } catch (\Throwable $e) {
            $this->deleteStorageImages($uploadedPaths);
            throw $e;
        }

        return $uploadedPaths;
    }

    /**
     * Upload, resize, and convert an image to WebP format.
     *
     * @throws \Exception
     */
    public function uploadImage(
        UploadedFile $file,
        string $folder,
        ?string $oldPath = null,
        int $maxSize = 800,
        bool $returnUrl = false
    ): string {
        if (! \function_exists('imagewebp') || (! \function_exists('imagecreatefromjpeg') && ! \function_exists('imagecreatefrompng') && ! \function_exists('imagecreatefromwebp'))) {
            throw new \Exception('GD extension with WebP support is required.');
        }

        if ($oldPath) {
            $resolvedOldPath = $this->resolveStoragePath($oldPath);

            if ($resolvedOldPath && Storage::disk('public')->exists($resolvedOldPath)) {
                Storage::disk('public')->delete($resolvedOldPath);
            }
        }

        if (! Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $generatedName = uniqid().'_'.Str::random(5).'.webp';
        $fullPath = "{$folder}/{$generatedName}";

        $imagePath = $file->getRealPath();

        if ($imagePath === false || ! file_exists($imagePath)) {
            throw new \Exception('Uploaded file is not readable or no longer available.');
        }

        $info = @\getimagesize($imagePath);

        if ($info === false) {
            throw new \Exception('Could not read image information. The file may be corrupted.');
        }

        $width = $info[0];
        $height = $info[1];

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => \imagecreatefromjpeg($imagePath),
            IMAGETYPE_PNG => \imagecreatefrompng($imagePath),
            IMAGETYPE_WEBP => \imagecreatefromwebp($imagePath),
            default => throw new \Exception('Image type not supported'),
        };

        if ($source === false) {
            throw new \Exception('Could not load the image for processing.');
        }

        if (\function_exists('imagepalettetotruecolor')) {
            \imagepalettetotruecolor($source);
        }

        \imagealphablending($source, true);
        \imagesavealpha($source, true);

        if ($width > $maxSize || $height > $maxSize) {
            if ($width > $height) {
                $newWidth = $maxSize;
                $newHeight = (int) ($height * ($maxSize / $width));
            } else {
                $newHeight = $maxSize;
                $newWidth = (int) ($width * ($maxSize / $height));
            }

            $resizedImage = \imagecreatetruecolor($newWidth, $newHeight);

            \imagealphablending($resizedImage, false);
            \imagesavealpha($resizedImage, true);

            \imagecopyresampled($resizedImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            \imagedestroy($source);
            $source = $resizedImage;
        }

        ob_start();
        \imagewebp($source, null, 80);
        $webpContent = ob_get_clean();
        \imagedestroy($source);

        if ($webpContent === false || $webpContent === '') {
            throw new \Exception('Failed to convert the image to WebP.');
        }

        $saved = Storage::disk('public')->put($fullPath, $webpContent);

        if (! $saved) {
            throw new \Exception('Failed to save the image to disk.');
        }

        return $returnUrl ? Storage::url($fullPath) : $fullPath;
    }
}
