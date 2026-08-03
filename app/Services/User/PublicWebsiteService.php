<?php

namespace App\Services\User;

use App\Models\Setting;
use App\Traits\HandleImageUploadTrait;
use Illuminate\Support\Facades\Storage;

class PublicWebsiteService
{
    use HandleImageUploadTrait;

    /**
     * Normalize a stored path or URL to a full public URL.
     */
    public function resolvePublicMediaUrl(string $pathOrUrl): string
    {
        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        $relativePath = $this->resolveStoragePath($pathOrUrl);

        if ($relativePath) {
            return Storage::disk('public')->url($relativePath);
        }

        return Storage::disk('public')->url(ltrim($pathOrUrl, '/'));
    }

    /**
     * Get all discovery image URLs (no shuffle).
     */
    public function getDiscoveryMediaUrls(): array
    {
        $images = Setting::get('discovery_media', []);

        if (! is_array($images) || empty($images)) {
            return [];
        }

        return array_map(
            fn (string $imagePath) => $this->resolvePublicMediaUrl($imagePath),
            array_filter($images, fn ($imagePath) => is_string($imagePath) && $imagePath !== '')
        );
    }

    /**
     * Get a random limited set of Discovery images for visitors
     */
    public function getDiscoveryMediaForVisitor(int $limit = 15): array
    {
        $fullUrlImages = $this->getDiscoveryMediaUrls();

        if (empty($fullUrlImages)) {
            return [];
        }

        shuffle($fullUrlImages);

        return array_slice($fullUrlImages, 0, $limit);
    }

    /**
     * Get the current hero image for visitors
     */
    public function getHeroImageForVisitor(): ?string
    {
        $heroImage = Setting::get('hero_image');

        if (! $heroImage || ! is_string($heroImage)) {
            return null;
        }

        return $this->resolvePublicMediaUrl($heroImage);
    }

    /**
     * Get the 3 images for the About section for visitors
     */
    public function getAboutMediaForVisitor(): array
    {
        $images = Setting::get('about_media', []);

        if (! is_array($images) || empty($images)) {
            return [];
        }

        return array_map(
            fn (string $imagePath) => $this->resolvePublicMediaUrl($imagePath),
            array_filter($images, fn ($imagePath) => is_string($imagePath) && $imagePath !== '')
        );
    }
}
