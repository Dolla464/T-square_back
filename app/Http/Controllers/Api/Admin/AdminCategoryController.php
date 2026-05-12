<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;

class AdminCategoryController extends Controller
{
    public function tree()
    {
        $categories = Category::whereNull('parent_id')
            // 1. تحديد الأعمدة للأقسام الرئيسية
            ->select(['id', 'name', 'slug'])

            // 2. تحديد الأعمدة للأبناء بشكل متداخل (Recursive Constraints)
            ->with(['children' => function ($query) {
                $query->select(['id', 'name', 'slug', 'parent_id'])
                    // نكرر المنطق للأبناء لضمان استمرار التداخل بنفس الأعمدة
                    ->with('children:id,name,slug,parent_id');
            }])
            ->get();

        return $this->successResponse($categories, 'Categories tree retrieved successfully');
    }
}
