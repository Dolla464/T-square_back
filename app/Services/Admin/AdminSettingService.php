<?php

namespace App\Services\Admin;

use App\Jobs\ProcessWebsiteMediaJob;
use App\Models\Setting;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
     * Raw files are persisted to the local disk immediately so the HTTP request
     * can return fast, then heavy resize + WebP conversion happens inside a
     * queued job (ProcessWebsiteMediaJob).
     *
     * @param  array<int, UploadedFile>  $images
     * @return array  Current images (before the job runs) – frontend polls for the new ones.
     */
    public function handleWebsiteMediaUpload(array $images, string $action, string $settingsKey, bool $isSingle = false): array
    {
        $currentImages = Setting::get($settingsKey, []);

        if ($isSingle) {
            $images        = array_slice($images, 0, 1);
            $currentImages = $currentImages ? [$currentImages] : [];
        } elseif (! is_array($currentImages)) {
            $currentImages = [];
        }

        if ($settingsKey === 'discovery_media' && $action === 'append') {
            $this->assertDiscoveryCapacity(count($currentImages), count($images));
        }

        $folder  = $this->resolveWebsiteMediaFolder($settingsKey);
        $maxSize = $settingsKey === 'discovery_media'
            ? self::DISCOVERY_MEDIA_MAX_SIZE
            : self::WEBSITE_MEDIA_MAX_SIZE;

        $oldImages  = ($action === 'replace' || $isSingle) ? (array) $currentImages : [];
        $baseImages = ($action === 'replace' || $isSingle) ? [] : (array) $currentImages;

        // Save raw files to the local disk so the request can return immediately.
        // PHP will delete the upload temp file after the response is sent, so we
        // must persist the content here before dispatching the job.
        $pendingPaths = [];
        foreach ($images as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $pendingName = 'pending/website-media/' . uniqid() . '_' . Str::random(5) . '.' . ($file->getClientOriginalExtension() ?: 'jpg');
            Storage::disk('local')->put($pendingName, $file->getContent());
            $pendingPaths[] = $pendingName;
        }

        if (! empty($pendingPaths)) {
            ProcessWebsiteMediaJob::dispatch(
                $pendingPaths,
                $settingsKey,
                $folder,
                $maxSize,
                $isSingle,
                $oldImages,
                $baseImages,
            )->onQueue('default');
        }

        // Return current images so the response is not empty while the job runs.
        // The frontend polls after receiving this response to pick up the new images.
        if ($isSingle) {
            return $currentImages ? [$currentImages[0]] : [];
        }

        return (array) $currentImages;
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
                    'Discovery gallery cannot exceed ' . self::DISCOVERY_MEDIA_MAX . " images. You can upload up to {$remaining} more image(s).",
                ],
            ]);
        }
    }

    protected function resolveWebsiteMediaFolder(string $settingsKey): string
    {
        return explode('_', $settingsKey)[0] ?? 'media';
    }
}
