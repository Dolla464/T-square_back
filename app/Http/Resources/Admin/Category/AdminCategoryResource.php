<?php

namespace App\Http\Resources\Admin\Category;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * This resource is intentionally restrictive: it exposes only the fields
     * required for admin show/edit views and the index listing.
     * No extra database columns leak through the API surface.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'status'      => $this->status,

            // Parent category details — only present when the relationship is loaded.
            'parent' => $this->whenLoaded('parent', fn () => [
                'id'   => $this->parent->id,
                'name' => $this->parent->name,
                'slug' => $this->parent->slug,
            ]),

            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
