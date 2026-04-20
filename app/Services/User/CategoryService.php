<?php

namespace App\Services\User;

use App\Models\Category;

class CategoryService
{
    /**
     * return categories in parent or sub categories
     */
    public function getCategories(array $params = [])
    {
        $query = Category::where('status', 'active');

        // return sub categories (like home page)
        if (isset($params['type']) && $params['type'] === 'sub') {
            $query->whereNotNull('parent_id');
        }

        // return parent categories (like courses page)
        if (isset($params['type']) && $params['type'] === 'parent') {
            $query->whereNull('parent_id');
        }

        return $query->orderBy('sort_order')
            ->select('id', 'name', 'slug', 'parent_id', 'icon')
            ->get();
    }

    /**
     * جلب تفاصيل قسم واحد (لو محتاج تعرض وصف القسم في صفحة لوحده)
     */
    // public function getCategoryBySlug($slug)
    // {
    //     return Category::where('slug', $slug)
    //         ->where('status', 'active')
    //         ->firstOrFail();
    // }
}
