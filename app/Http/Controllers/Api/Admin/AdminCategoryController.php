<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Admin\AdminCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Admin: Categories
 */
class AdminCategoryController extends Controller
{
    public function __construct(
        private readonly AdminCategoryService $categoryService,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // Index — child categories only, with search + parent filter + pagination
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /admin/categories
     *
     * Query parameters:
     *   - search    (string)  : fuzzy search across name, slug, and description.
     *   - parent_id (integer) : narrow results to a specific parent's children.
     *   - perPage   (integer) : items per page, defaults to 10.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getChildCategories(
            perPage : (int) $request->get('perPage', 10),
            search  : $request->get('search'),
            parentId: $request->integer('parent_id') ?: null,
        );

        return $this->paginateResponse($categories, 'Categories retrieved successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Store
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /admin/categories
     *
     * Slug is generated automatically from the name by the Category model.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->createCategory($request->validated());

        return $this->successResponse($category, 'Category created successfully', 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Show
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /admin/categories/{category}
     *
     * Returns a tightly restricted payload: name, description, parent, status.
     */
    public function show(Category $category): JsonResponse
    {
        $resource = $this->categoryService->getCategoryDetails($category);

        return $this->successResponse($resource, 'Category retrieved successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Update
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * PUT/PATCH /admin/categories/{category}
     *
     * Slug is regenerated automatically only when the name changes.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $resource = $this->categoryService->updateCategory($category, $request->validated());

        return $this->successResponse($resource, 'Category updated successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tree (read-only utility — exists prior to this implementation)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /admin/categories/tree
     *
     * Returns the full nested category tree for parent-picker dropdowns.
     * This route is registered BEFORE the apiResource to prevent the {category}
     * wildcard from capturing the "tree" segment.
     */
    public function tree(): JsonResponse
    {
        $categories = Category::query()->whereNull('parent_id', 'and', false)
            ->select(['id', 'name', 'slug', 'status', 'created_at'])
            ->withCount('children')
            ->with(['children' => function ($query) {
                $query->select(['id', 'name', 'slug', 'parent_id'])
                    ->with('children:id,name,slug,parent_id');
            }])
            ->get()
            ->map(fn (Category $category) => [
                'id'             => $category->id,
                'name'           => $category->name,
                'slug'           => $category->slug,
                'status'         => $category->status,
                'created_at'     => $category->created_at?->format('Y-m-d'),
                'children_count' => $category->children_count,
                'children'       => $category->children,
            ]);

        return $this->successResponse($categories, 'Categories tree retrieved successfully');
    }
}
