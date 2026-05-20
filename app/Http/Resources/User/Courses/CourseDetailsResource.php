<?php

namespace App\Http\Resources\User\Courses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'image' => $this->thumbnail,
            'level' => $this->level,
            'duration_hours' => $this->duration_hours,
            'duration_weeks' => $this->duration_weeks,
            'cover_image' => $this->cover_image,
            'price' => [
                'final' => (int) ($this->price),
                'currency' => 'EGP',
            ],

            'category' => [
                'id' => $this->category->id ?? null,
                'name' => $this->category->name ?? null,
                'slug' => $this->category->slug ?? null,
            ],

            'instructor' => [
                'id' => $this->instructor->id ?? null,
                'name' => $this->instructor->user->name ?? null,
            ],

            // مصفوفة "ماذا ستتعلم"
            'learnings' => $this->whenLoaded('learnings', fn () => $this->learnings->pluck('title'), []),

            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            })->values(), []),

            // مصفوفة الفيديوهات أو الملفات التجريبية
            'previews' => $this->whenLoaded('previews', fn () => $this->previews->map(function ($preview) {
                return [
                    'id' => $preview->id,
                    'title' => $preview->title,
                    'video_url' => $preview->video_url,
                    'description' => $preview->description,
                    'video_provider' => $preview->video_provider,
                    'duration_seconds' => $preview->duration_seconds,
                    'sort_order' => $preview->sort_order,
                ];
            })->values(), []),
        ];
    }
}
