<?php

namespace App\Services\User;

use App\Models\Setting;

class PublicWebsiteService
{
    /**
     * 1. Get a random limited set of Discovery images for visitors
     */
    public function getDiscoveryMediaForVisitor(int $limit = 15): array
    {
        $images = Setting::get('discovery_media', []);

        if (!is_array($images) || empty($images)) {
            return [];
        }

        $fullUrlImages = array_map(function ($imagePath) {
            // If the path is stored as /storage/.. or storage/.. we convert it to a full URL
            $cleanPath = str_replace('/storage/', '', $imagePath);
            return \Illuminate\Support\Facades\Storage::disk('public')->url($cleanPath);
        }, $images);

        // Shuffle the images randomly to ensure a fresh set with each visit
        shuffle($fullUrlImages);

        return array_slice($fullUrlImages, 0, $limit);
    }

    /**
     * Get the current hero image for visitors
     */
    public function getHeroImageForVisitor(): ?string
    {
        $heroImage = Setting::get('hero_image');

        if (!$heroImage) {
            return null; // The frontend will display the default image if it returns null
        }

        $cleanPath = str_replace('/storage/', '', $heroImage);
        return \Illuminate\Support\Facades\Storage::disk('public')->url($cleanPath);
    }

    /**
     * Get the 3 images for the About section for visitors
     */
    public function getAboutMediaForVisitor(): array
    {
        $images = Setting::get('about_media', []);

        if (!is_array($images) || empty($images)) {
            return [];
        }

        // Convert all paths to full URLs with the domain
        return array_map(function ($imagePath) {
            $cleanPath = str_replace('/storage/', '', $imagePath);
            return \Illuminate\Support\Facades\Storage::disk('public')->url($cleanPath);
        }, $images);
    }
}
