<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @tags Admin: Tags
 */
class AdminTagController extends Controller
{
    public function index()
    {
        $tags = Tag::select('id', 'name', 'slug', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($tags, 'Tags retrieved successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tags,name'],
        ]);

        $data['slug'] = Str::slug($data['name']);

        // Ensure slug is also unique
        $originalSlug = $data['slug'];
        $count = 1;
        while (Tag::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $count++;
        }

        $tag = Tag::create($data);

        return $this->successResponse($tag, 'Tag created successfully', 201);
    }

    public function update(Request $request, Tag $tag)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('tags', 'name')->ignore($tag->id)],
        ]);

        $data['slug'] = Str::slug($data['name']);

        // Ensure slug is unique (excluding current tag)
        $originalSlug = $data['slug'];
        $count = 1;
        while (Tag::where('slug', $data['slug'])->where('id', '!=', $tag->id)->exists()) {
            $data['slug'] = $originalSlug . '-' . $count++;
        }

        $tag->update($data);

        return $this->successResponse($tag->fresh(), 'Tag updated successfully');
    }

    public function destroy(Tag $tag)
    {
        $coursesCount  = $tag->courses()->count();
        $solutionsCount = $tag->solutions()->count();

        if ($coursesCount > 0 || $solutionsCount > 0) {
            return $this->errorResponse(
                'Cannot delete tag because it is used by ' . ($coursesCount + $solutionsCount) . ' item(s).',
                422
            );
        }

        $tag->delete();

        return $this->successResponse(null, 'Tag deleted successfully');
    }
}
