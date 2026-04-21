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
            'image' => $this->thumbnail,
            'attendance_type' => $this->attendance_type,
            'short_description' => $this->short_description,
            'duration_hours' => $this->duration_hours,
            'duration_weeks' => $this->duration_weeks,
            'price' => [
                'original' => (int)$this->price_before,
                'discount' => (int)$this->discount_price,
                'final' => (int)$this->price,
            ],
            'category' => $this->category->name ?? null,
            'tags' => $this->tags->take(4)->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ];
            }),
        ];
    }
}
