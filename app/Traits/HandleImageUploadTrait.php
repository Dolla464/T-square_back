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
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param string|null $oldPath
     * @return string
     */
    public function uploadImage(UploadedFile $file, string $folder, ?string $oldPath = null): string
    {
        // 1. delete the old image
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // 2. prepare the name and path
        $generatedName = $folder . '_' . time() . '_' . Str::random(5) . '.webp';
        $fullPath = "{$folder}/{$generatedName}";

        // 3. read the original image
        $imagePath = $file->getRealPath();
        $info = \getimagesize($imagePath);
        $width = $info[0];
        $height = $info[1];

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => \imagecreatefromjpeg($imagePath),
            IMAGETYPE_PNG  => \imagecreatefrompng($imagePath),
            IMAGETYPE_WEBP => \imagecreatefromwebp($imagePath),
            default        => throw new \Exception('Image type not supported'),
        };

        // --- step to determine the new dimensions (Resize) ---
        $maxSide = 800; // maximum width or height of the image
        if ($width > $maxSide || $height > $maxSide) {
            if ($width > $height) {
                $newWidth = $maxSide;
                $newHeight = (int)($height * ($maxSide / $width));
            } else {
                $newHeight = $maxSide;
                $newWidth = (int)($width * ($maxSide / $height));
            }

            // create an empty image with the new dimensions
            $resizedImage = \imagecreatetruecolor($newWidth, $newHeight);
            // keep the transparency (if it's a PNG)
            \imagealphablending($resizedImage, false);
            \imagesavealpha($resizedImage, true);

            // copy and resize the image
            \imagecopyresampled($resizedImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            \imagedestroy($source);
            $source = $resizedImage;
        }
        // -------------------------------------------

        // 4. convert the image to WebP using Buffer
        ob_start();
        \imagewebp($source, null, 80);
        $webpContent = ob_get_clean();
        \imagedestroy($source);

        // 5. save the image
        Storage::disk('public')->put($fullPath, $webpContent);

        return $fullPath;
    }
}
