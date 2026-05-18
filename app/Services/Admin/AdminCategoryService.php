<?php

namespace App\Services\Admin;

use App\Http\Resources\Admin\Category\AdminCategoryResource;
use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminCategoryService
{
    // ──────────────────────────────────────────────────────────────────────────
    // Index — paginated child categories with multi-filter support
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return a paginated list of child categories (parent_id IS NOT NULL).
     *
     * Supported filters:
     *   - search   : fuzzy match against name, slug, and description simultaneously.
     *   - parent_id: restrict results to children of a specific parent category.
     *   - perPage  : number of records per page (defaults to 10).
     */
    public function getChildCategories(
        int $perPage = 10,
        ?string $search = null,
        ?int $parentId = null,
    ): LengthAwarePaginator {
        $query = Category::with('parent:id,name,slug')
            ->whereNotNull('parent_id');

        // 1. Fuzzy search across name, slug, and description.
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name',        'LIKE', "%{$search}%")
                  ->orWhere('slug',        'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // 2. Filter by a specific parent category.
        if (!empty($parentId)) {
            $query->where('parent_id', $parentId);
        }

        $categories = $query->latest()->paginate($perPage);

        // Transform each paginated item into the API Resource.
        $categories->getCollection()->transform(
            fn (Category $category) => new AdminCategoryResource($category)
        );

        return $categories;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Store
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a new category.
     * The slug is generated automatically by the model's `creating` hook.
     */
    public function createCategory(array $data): AdminCategoryResource
    {
        $category = Category::create($data);

        // Eager-load the parent so the resource can expose it immediately.
        $category->load('parent:id,name,slug');

        return new AdminCategoryResource($category);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Show
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Retrieve a single category with its parent relationship for view/edit.
     */
    public function getCategoryDetails(Category $category): AdminCategoryResource
    {
        $category->load('parent:id,name,slug');

        return new AdminCategoryResource($category);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Update
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Update a category's data.
     * The slug is regenerated automatically by the model's `updating` hook
     * only when the name has actually changed.
     */
    public function updateCategory(Category $category, array $data): AdminCategoryResource
    {
        $category->update($data);

        $category->load('parent:id,name,slug');

        return new AdminCategoryResource($category);
    }
}
