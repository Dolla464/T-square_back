<?php

namespace App\Http\Resources\User\Courses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseListResource extends JsonResource
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
            'image' => $this->cover_image,
            'attendance_type' => $this->attendance_type,
            'short_description' => $this->short_description,
            'duration_hours' => $this->duration_hours,
            'duration_weeks' => $this->duration_weeks,
            'price' => [
                'original' => (int) $this->price_before,
                'discount' => (int) $this->discount_price,
                'final' => (int) $this->price,
            ],
            'category' => $this->category->name ?? null,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->take(4)->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            })->values(), []),
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
