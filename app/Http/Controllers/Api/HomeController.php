<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\User\HomeService;
use Illuminate\Http\JsonResponse;

/**
 * @tags Public
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly HomeService $homeService,
    ) {}

    /**
     * Aggregated home page data (hero, about, discovery, courses, testimonials).
     *
     * GET /api/home
     */
    public function index(): JsonResponse
    {
        return $this->successResponse(
            $this->homeService->getHomePageData(),
            'Home page data retrieved successfully',
        );
    }
}
