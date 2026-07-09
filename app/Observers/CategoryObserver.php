<?php

namespace App\Observers;

use App\Models\Category;
use App\Support\HomePageCache;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        HomePageCache::forget();
    }

    public function deleted(Category $category): void
    {
        HomePageCache::forget();
    }
}
