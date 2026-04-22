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
        ];
    }
}
