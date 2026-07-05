<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UploadDiscoveryMediaRequest;
use App\Http\Resources\Admin\Settings\DiscoveryMediaResource;
use App\Models\Setting;
use App\Services\Admin\AdminSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Admin: Media
 */
class AdminDiscoveryMediaController extends Controller
{
    protected AdminSettingService $settingService;

    // Inject the Service into the Controller through the Constructor
    public function __construct(AdminSettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function index(): JsonResponse
    {
        $currentImages = Setting::get('discovery_media', []);

        return $this->successResponse(
            new DiscoveryMediaResource($currentImages),
            'Discovery media retrieved successfully',
            200
        );
    }

    public function upload(UploadDiscoveryMediaRequest $request): JsonResponse
    {
        $settingsKey = $request->input('key');
        $isSingle = ($settingsKey === 'hero_image');

        $finalData = $this->settingService->handleWebsiteMediaUpload(
            $request->file('images'),
            $request->input('action'),
            $settingsKey,
            $isSingle
        );
        return $this->successResponse(
            new DiscoveryMediaResource($finalData),
            $request->input('action') === 'replace'
                ? 'Old images replaced and new images uploaded successfully!'
                : 'New images added to the current images successfully!',
            200
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'image_url' => 'required|string',
            'key' => 'required|string|in:discovery_media,about_media'
        ]);
    
        $updatedImages = $this->settingService->deleteSingleWebsiteImage(
            $request->input('image_url'),
            $request->input('key')
        );
    
        return $this->successResponse(
            $updatedImages,
            'Image deleted successfully from the current ' . $request->input('key') . ' media!',
            200
        );
    }
}
