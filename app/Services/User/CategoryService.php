<?php

namespace App\Services\User;

use App\Models\Category;

class CategoryService
{
    /**
     * جلب الأقسام الرئيسية مع أبنائها النشطين فقط
     */
    public function getCategoryTree()
    {
        return Category::whereNull('parent_id')
            ->where('status', 'active')
            ->with(['children' => function($query) {
                $query->where('status', 'active')
                      ->select('id', 'name', 'slug', 'parent_id', 'icon')
                      ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'icon']);
    }

    /**
     * جلب تفاصيل قسم واحد (لو محتاج تعرض وصف القسم في صفحة لوحده)
     */
    public function getCategoryBySlug($slug)
    {
        return Category::where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();
    }
}