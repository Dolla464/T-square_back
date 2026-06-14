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
     * Upload, resize, and convert an image to WebP format.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param string|null $oldPath
     * @param int $maxSize
     * @return string
     * @throws \Exception
     */
    public function uploadImage(UploadedFile $file, string $folder, ?string $oldPath = null, int $maxSize = 800): string
    {
        // 1. Ensure required GD functions are available
        if (! \function_exists('imagewebp') || (! \function_exists('imagecreatefromjpeg') && ! \function_exists('imagecreatefrompng') && ! \function_exists('imagecreatefromwebp'))) {
            throw new \Exception('GD extension with WebP support is required.');
        }

        // 2. Delete the old image if it exists
        // نُحوّل الـ URL الكامل إلى مسار نسبي (لقواعد البيانات القديمة التي خزنت URL كامل)
        if ($oldPath) {
            $storagePublicUrl = Storage::disk('public')->url('');
            if (str_starts_with($oldPath, $storagePublicUrl)) {
                $oldPath = ltrim(substr($oldPath, strlen($storagePublicUrl)), '/');
            } elseif (str_starts_with($oldPath, 'http://') || str_starts_with($oldPath, 'https://')) {
                // URL كامل لكن من domain مختلف أو نمط مختلف - نتجاهله آمنًا
                $oldPath = null;
            }
        }

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // 3. Ensure target directory exists
        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }
        
        // 4. Prepare the name and path
        $generatedName = uniqid() . '_' . Str::random(5) . '.webp';
        $fullPath = "{$folder}/{$generatedName}";

        // 5. Read the original image
        $imagePath = $file->getRealPath();

        if ($imagePath === false || ! file_exists($imagePath)) {
            throw new \Exception('Uploaded file is not readable or no longer available.');
        }

        $info = \getimagesize($imagePath);

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

        // 6. Resize if necessary while maintaining aspect ratio
        if ($width > $maxSize || $height > $maxSize) {
            if ($width > $height) {
                $newWidth = $maxSize;
                $newHeight = (int) ($height * ($maxSize / $width));
            } else {
                $newHeight = $maxSize;
                $newWidth = (int) ($width * ($maxSize / $height));
            }

            $resizedImage = \imagecreatetruecolor($newWidth, $newHeight);
            
            // Maintain transparency for PNG/WebP
            \imagealphablending($resizedImage, false);
            \imagesavealpha($resizedImage, true);

            \imagecopyresampled($resizedImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            \imagedestroy($source);
            $source = $resizedImage;
        }

        // 7. Convert to WebP using Buffer
        ob_start();
        \imagewebp($source, null, 80); // quality 80%
        $webpContent = ob_get_clean();
        \imagedestroy($source);

        // 8. Save the image and verify
        $saved = Storage::disk('public')->put($fullPath, $webpContent);
        
        if (!$saved) {
            throw new \Exception('Failed to save the image to disk.');
        }

        return $fullPath;
    }
}