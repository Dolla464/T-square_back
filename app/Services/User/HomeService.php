<?php

namespace App\Services\User;

use App\Http\Resources\User\CourseReview\CourseReviewResource;
use App\Http\Resources\User\Courses\CourseListResource;
use App\Models\Setting;
use App\Support\HomePageCache;
use Illuminate\Support\Facades\Cache;

class HomeService
{
    private const HERO_SETTING_KEYS = [
        'hero_title_en',
        'hero_title_ar',
        'hero_title_highlight_en',
        'hero_title_highlight_ar',
        'hero_subtitle_en',
        'hero_subtitle_ar',
    ];

    public function __construct(
        private readonly PublicWebsiteService $publicWebsiteService,
        private readonly CategoryService $categoryService,
        private readonly CourseService $courseService,
        private readonly CourseReviewService $courseReviewService,
    ) {}

    public function getHomePageData(): array
    {
        $cached = Cache::remember(HomePageCache::KEY, now()->addMinutes(15), function () {
            return $this->buildCachedData();
        });

        $data = $cached;
        $discoveryImages = $data['discovery']['images'] ?? [];

        if (! empty($discoveryImages)) {
            shuffle($discoveryImages);
            $data['discovery']['images'] = array_slice($discoveryImages, 0, 15);
        }

        return $data;
    }

    private function buildCachedData(): array
    {
        $coursesPaginator = $this->courseService->getActiveCourses(['per_page' => 6]);

        return [
            'hero' => [
                'image' => $this->publicWebsiteService->getHeroImageForVisitor(),
                'settings' => $this->getHeroSettings(),
            ],
            'about' => [
                'images' => $this->publicWebsiteService->getAboutMediaForVisitor(),
            ],
            'discovery' => [
                'images' => $this->publicWebsiteService->getDiscoveryMediaUrls(),
            ],
            'courses' => [
                'categories' => $this->categoryService->getCategories(['type' => 'sub']),
                'items' => CourseListResource::collection($coursesPaginator->items())->resolve(),
                'meta' => [
                    'current_page' => $coursesPaginator->currentPage(),
                    'last_page' => $coursesPaginator->lastPage(),
                    'total' => $coursesPaginator->total(),
                ],
            ],
            'testimonials' => CourseReviewResource::collection(
                $this->courseReviewService->getPublicFeaturedReviews()
            )->resolve(),
        ];
    }

    private function getHeroSettings(): array
    {
        $settings = Setting::whereIn('key', self::HERO_SETTING_KEYS)
            ->get()
            ->keyBy('key');

        $result = [];

        foreach (self::HERO_SETTING_KEYS as $key) {
            $result[$key] = $settings->has($key) ? (string) $settings[$key]->value : '';
        }

        return $result;
    }
}
