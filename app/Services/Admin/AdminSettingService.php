<?php

namespace App\Services\Admin;

use App\Models\Setting;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AdminSettingService
{
    use HandleImageUploadTrait;

    public const DISCOVERY_MEDIA_MAX = 30;

    public const DISCOVERY_MEDIA_MAX_SIZE = 1200;

    public const WEBSITE_MEDIA_MAX_SIZE = 1200;

    /**
     * Handle the upload and save of website media images (dynamic for hero, about, discovery)
     *
     * @param  array<int, UploadedFile>  $images
     */
    public function handleWebsiteMediaUpload(array $images, string $action, string $settingsKey, bool $isSingle = false): array
    {
        $currentImages = Setting::get($settingsKey, []);

        if ($isSingle) {
            $images = array_slice($images, 0, 1);
            $currentImages = $currentImages ? [$currentImages] : [];
        } elseif (! is_array($currentImages)) {
            $currentImages = [];
        }

        if ($settingsKey === 'discovery_media' && $action === 'append') {
            $this->assertDiscoveryCapacity(count($currentImages), count($images));
        }

        $folder = $this->resolveWebsiteMediaFolder($settingsKey);
        $maxSize = $settingsKey === 'discovery_media'
            ? self::DISCOVERY_MEDIA_MAX_SIZE
            : self::WEBSITE_MEDIA_MAX_SIZE;

        $oldImages = ($action === 'replace' || $isSingle) ? (array) $currentImages : [];
        $baseImages = ($action === 'replace' || $isSingle) ? [] : (array) $currentImages;

        $newUploadedPaths = $this->uploadImagesBatch($images, $folder, $maxSize, returnUrls: true);

        if (! empty($oldImages)) {
            $this->deleteStorageImages($oldImages);
        }

        if ($isSingle) {
            $finalData = $newUploadedPaths[0] ?? null;
            Setting::set($settingsKey, $finalData, 'string', 'general');

            return $finalData ? [$finalData] : [];
        }

        $finalData = array_merge($baseImages, $newUploadedPaths);
        Setting::set($settingsKey, $finalData, 'json', 'general');

        return $finalData;
    }

    /**
     * Delete a specific single image from any array section (dynamic completely)
     */
    public function deleteSingleWebsiteImage(string $imageUrl, string $settingsKey): array
    {
        $currentImages = Setting::get($settingsKey, []);

        if (! is_array($currentImages)) {
            $currentImages = [];
        }

        if (($key = array_search($imageUrl, $currentImages)) !== false) {
            $relativePath = $this->resolveStoragePath($imageUrl);

            if ($relativePath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($relativePath);
            }

            unset($currentImages[$key]);
            $currentImages = array_values($currentImages);

            Setting::set($settingsKey, $currentImages, 'json', 'general');
        }

        return $currentImages;
    }

    /**
     * @throws ValidationException
     */
    protected function assertDiscoveryCapacity(int $currentCount, int $incomingCount): void
    {
        if ($currentCount + $incomingCount > self::DISCOVERY_MEDIA_MAX) {
            $remaining = max(0, self::DISCOVERY_MEDIA_MAX - $currentCount);

            throw ValidationException::withMessages([
                'images' => [
                    "Discovery gallery cannot exceed ".self::DISCOVERY_MEDIA_MAX." images. You can upload up to {$remaining} more image(s).",
                ],
            ]);
        }
    }

    protected function resolveWebsiteMediaFolder(string $settingsKey): string
    {
        return explode('_', $settingsKey)[0] ?? 'media';
    }
}
