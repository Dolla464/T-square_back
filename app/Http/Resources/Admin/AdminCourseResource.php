<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCourseResource extends JsonResource
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
            'short_description' => $this->short_description,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'cover_image' => $this->cover_image,
            'preview_video' => $this->preview_video,
            'google_drive_link' => $this->google_drive_link,
            'attendance_type' => $this->attendance_type,
            'price_before' => $this->price_before,
            'discount_price' => $this->discount_price,
            'price' => $this->price,
            'level' => $this->level,
            'language' => $this->language,
            'duration_weeks' => $this->duration_weeks,
            'duration_hours' => $this->duration_hours,
            'status' => $this->status,
            'is_featured' => (bool) $this->is_featured,
            'is_free' => (bool) $this->is_free,
            'category_id' => $this->category_id,
            'instructor_id' => $this->instructor_id,
            'published_at' => $this->published_at,
            'avg_rating' => $this->avg_rating,
            'total_reviews' => $this->total_reviews,
            'total_students' => $this->total_students,
            'total_revenue' => $this->total_revenue,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,

            // Relationships (only returned when they are loaded)
            'instructor' => $this->whenLoaded('instructor', function () {
                $instructor = $this->instructor;
                if (!$instructor) {
                    return null;
                }

                return [
                    'id' => $instructor->id ?? null,
                    'full_name' => $instructor->full_name ?? null,
                    'phone' => $instructor->phone ?? null,
                    'field' => $instructor->field ?? null,
                ];
            }),

            'category' => $this->whenLoaded('category', function () {
                $category = $this->category;
                if (!$category) {
                    return null;
                }

                return [
                    'id' => $category->id ?? null,
                    'name' => $category->name ?? null,
                    'icon' => $category->icon ?? null,
                ];
            }),
        ];
    }
}
