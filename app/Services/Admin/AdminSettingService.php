<?php

namespace App\Services\Admin;

use App\Models\Setting;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminSettingService
{
    use HandleImageUploadTrait;

    public const DISCOVERY_MEDIA_MAX = 30;

    public const DISCOVERY_MEDIA_MAX_SIZE = 1200;

    public const WEBSITE_MEDIA_MAX_SIZE = 1200;

    /**
     * Handle the upload and save of website media images (dynamic for hero, about, discovery).
     *
     * Processing is synchronous and all-or-nothing: a single invalid image rolls back
     * the entire batch and leaves existing settings/files unchanged.
     *
     * @param  array<int, UploadedFile>  $images
     * @return array<int, string>
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

        $uploadedUrls = $this->uploadImagesBatch($images, $folder, $maxSize, returnUrls: true);

        if ($isSingle) {
            $finalData = $uploadedUrls[0];
            Setting::set($settingsKey, $finalData, 'string', 'general');

            if (! empty($oldImages)) {
                $this->deleteStorageImages($oldImages);
            }

            return [$finalData];
        }

        $finalData = array_merge($baseImages, $uploadedUrls);
        Setting::set($settingsKey, $finalData, 'json', 'general');

        if (! empty($oldImages)) {
            $this->deleteStorageImages($oldImages);
        }

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
                Storage::disk('public')->delete($relativePath);
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
                    'Discovery gallery cannot exceed '.self::DISCOVERY_MEDIA_MAX." images. You can upload up to {$remaining} more image(s).",
                ],
            ]);
        }
    }

    protected function resolveWebsiteMediaFolder(string $settingsKey): string
    {
        return explode('_', $settingsKey)[0] ?? 'media';
    }
}
