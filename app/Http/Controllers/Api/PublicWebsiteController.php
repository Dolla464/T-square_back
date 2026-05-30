<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\User\PublicWebsiteService;
use App\Http\Resources\User\PublicWebsite\DiscoveryMediaResource;
use Illuminate\Http\JsonResponse;

class PublicWebsiteController extends Controller
{
    protected PublicWebsiteService $publicWebsiteService;

    // Inject the PublicWebsiteService through the Constructor
    public function __construct(PublicWebsiteService $publicWebsiteService)
    {
        $this->publicWebsiteService = $publicWebsiteService;
    }

    /**
     * Get media for any section dynamically based on the key
     */
    public function getMediaByKey(string $key): JsonResponse
    {
        // Check if the key is allowed for data protection
        $allowedKeys = ['discovery_media', 'about_media', 'hero_image'];
        if (!in_array($key, $allowedKeys)) {
            return response()->json(['success' => false, 'message' => 'Invalid section key'], 400);
        }

        // If it's for hero (single image)
        if ($key === 'hero_image') {
            $data = $this->publicWebsiteService->getHeroImageForVisitor();
            return $this->successResponse(
                ['hero_image' => $data],
                'Hero image retrieved successfully',
                200
            );
        }

        // If it's for about (we need 3 images but no random shuffling)
        if ($key === 'about_media') {
            $data = $this->publicWebsiteService->getAboutMediaForVisitor();
            return $this->successResponse(
                ['about_images' => $data],
                'About images retrieved successfully',
                200
            );
        }

        // If it's for discovery (15 random images)
        $data = $this->publicWebsiteService->getDiscoveryMediaForVisitor(15);
        return $this->successResponse(
            ['images' => $data],
            'Discovery images retrieved successfully',
            200
        );
    }
}
