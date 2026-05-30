<?php

namespace App\Services\Admin;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminSettingService
{
    /**
     * Handle the upload and save of website media images (dynamic for hero, about, discovery)
     */
    public function handleWebsiteMediaUpload(array $images, string $action, string $settingsKey, bool $isSingle = false): array
    {
        // Get the current data based on the passed Key
        $currentImages = Setting::get($settingsKey, []);
        
        // Check the type of stored data
        if ($isSingle) {
            $currentImages = $currentImages ? [$currentImages] : [];
        } elseif (!is_array($currentImages)) {
            $currentImages = [];
        }

        // If the action is replace or a single image, delete the old files from the server
        if ($action === 'replace' || $isSingle) {
            $this->deletePhysicalImages((array)$currentImages);
            $currentImages = [];
        }

        $newUploadedPaths = [];
        foreach ($images as $image) {
            // We passed the settingsKey here to automatically determine the save folder
            $path = $this->uploadAndConvert($image, $settingsKey); 
            if ($path) {
                $newUploadedPaths[] = $path;
            }
        }

        // Save the data in the database based on the field type (single image or array)
        if ($isSingle) {
            $finalData = $newUploadedPaths[0] ?? null;
            Setting::set($settingsKey, $finalData, 'string', 'general');
            return $finalData ? [$finalData] : [];
        } else {
            $finalData = array_merge((array)$currentImages, $newUploadedPaths);
            Setting::set($settingsKey, $finalData, 'json', 'general');
            return $finalData;
        }
    }

    /**
     * Convert the image to WebP and save it in a dynamic folder
     */
    private function uploadAndConvert(UploadedFile $file, string $folderName): ?string
    {
        $fileName = Str::uuid() . '.webp';
        
        // Clean the key name for use as a folder name (e.g. hero_image will become hero)
        $cleanFolder = explode('_', $folderName)[0] ?? 'media';
        $destinationPath = $cleanFolder . '/' . $fileName;

        $compressedImage = $this->convertToWebp($file, 80);

        if ($compressedImage) {
            Storage::disk('public')->put($destinationPath, $compressedImage);
            return Storage::url($destinationPath);
        }

        // Fallback in case of GD failure
        $path = $file->store($cleanFolder, 'public');
        return Storage::url($path);
    }

    /**
     * Convert the image to native WebP to improve performance
     */
    private function convertToWebp(UploadedFile $file, int $quality): ?string
    {
        $imagePath = $file->getRealPath();
        $mime = $file->getMimeType();

        $image = match ($mime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($imagePath),
            'image/png' => imagecreatefrompng($imagePath),
            'image/webp' => imagecreatefromwebp($imagePath),
            default => null,
        };

        if (!$image) {
            return null;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, $quality);
        $imageData = ob_get_clean();

        imagedestroy($image);

        return $imageData;
    }

    /**
     * Delete the actual files from the server
     */
    private function deletePhysicalImages(array $imageUrls): void
    {
        foreach ($imageUrls as $url) {
            if (empty($url) || !is_string($url)) continue;
            $relativePath = str_replace('/storage/', '', $url);
            Storage::disk('public')->delete($relativePath);
        }
    }

    /**
     * Delete a specific single image from any array section (dynamic completely)
     */
    public function deleteSingleWebsiteImage(string $imageUrl, string $settingsKey): array
    {
        // Get the current array dynamically
        $currentImages = Setting::get($settingsKey, []);
        if (!is_array($currentImages)) {
            $currentImages = [];
        }

        // Search for the image and delete it
        if (($key = array_search($imageUrl, $currentImages)) !== false) {

            // Delete the actual file from the server
            $relativePath = str_replace('/storage/', '', $imageUrl);
            Storage::disk('public')->delete($relativePath);

            // Delete the link and reorder the indexes
            unset($currentImages[$key]);
            $currentImages = array_values($currentImages);

            // Update the database with the specific key
            Setting::set($settingsKey, $currentImages, 'json', 'general');
        }

        return $currentImages;
    }
}