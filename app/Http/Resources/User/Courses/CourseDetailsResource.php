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
            'language' => $this->language,
            'duration' => $this->duration, // مثلاً بالساعات
            
            'price' => [
                'original' => (float) $this->price_before,
                'discount' => (float) $this->discount_price,
                'final' => (float) ($this->price_before - $this->discount_price),
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
                'bio' => $this->instructor->bio ?? null,
                'avatar' => $this->instructor->avatar ? asset('storage/' . $this->instructor->avatar) : null,
            ],

            // مصفوفة "ماذا ستتعلم"
            'learnings' => $this->learnings->pluck('content'), 

            // مصفوفة الفيديوهات أو الملفات التجريبية
            'previews' => $this->previews->map(function($preview) {
                return [
                    'id' => $preview->id,
                    'title' => $preview->title,
                    'url' => $preview->video_url,
                    'is_free' => (bool) $preview->is_free,
                ];
            }),

            // التاجز
            'tags' => $this->tags->map(function($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ];
            }),

            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
