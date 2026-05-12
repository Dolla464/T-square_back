<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Trait to handle image upload.
 */
trait HandleImageUploadTrait
{
    /**
     * Upload an image to the public disk.
     */
    /**
     * Upload, resize, and convert an image to WebP format.
     *
     * @param  string|null  $oldPath  Path of the old file to delete.
     * @param  int  $maxSize  Max width/height in pixels (800 for thumbnails, 1920 for covers).
     */
    public function uploadImage(UploadedFile $file, string $folder, ?string $oldPath = null, int $maxSize = 800): string
    {
        // ensure required GD functions are available (and WebP support)
        if (! \function_exists('imagewebp') || (! \function_exists('imagecreatefromjpeg') && ! \function_exists('imagecreatefrompng') && ! \function_exists('imagecreatefromwebp'))) {
            throw new \Exception('GD extension with image and WebP support is required. Please enable the gd extension and ensure WebP support is available in your PHP build.');
        }

        // 1. delete the old image
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // 2. prepare the name and path
        $generatedName = $folder.'_'.time().'_'.Str::random(5).'.webp';
        $fullPath = "{$folder}/{$generatedName}";

        // 3. read the original image
        $imagePath = $file->getRealPath();
        $info = \getimagesize($imagePath);
        $width = $info[0];
        $height = $info[1];

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => \imagecreatefromjpeg($imagePath),
            IMAGETYPE_PNG => \imagecreatefrompng($imagePath),
            IMAGETYPE_WEBP => \imagecreatefromwebp($imagePath),
            default => throw new \Exception('Image type not supported'),
        };

        // --- Resize to $maxSize while maintaining aspect ratio ---
        $maxSide = $maxSize;
        if ($width > $maxSide || $height > $maxSide) {
            if ($width > $height) {
                $newWidth = $maxSide;
                $newHeight = (int) ($height * ($maxSide / $width));
            } else {
                $newHeight = $maxSide;
                $newWidth = (int) ($width * ($maxSide / $height));
            }

            // create an empty image with the new dimensions
            $resizedImage = \imagecreatetruecolor($newWidth, $newHeight);

            // keep the transparency (if it's a PNG)
            \imagealphablending($resizedImage, false);
            \imagesavealpha($resizedImage, true);

            // copy and resize the image
            \imagecopyresampled($resizedImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            \imagedestroy($source); // delete the old image from memory to free up space
            $source = $resizedImage;
        }
        // -------------------------------------------

        // 4. convert the image to WebP using Buffer
        ob_start();
        \imagewebp($source, null, 80); // quality 80%
        $webpContent = ob_get_clean();
        \imagedestroy($source);

        // 5. save the image
        Storage::disk('public')->put($fullPath, $webpContent);

        return $fullPath;
    }
}
