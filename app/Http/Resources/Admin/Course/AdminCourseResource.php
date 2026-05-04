<?php

namespace App\Http\Resources\Admin\Course;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\Course\CourseFieldList;

class AdminCourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // determine which fields should be considered for listing vs detail
        $fields = $request->routeIs('*show*') 
                ? CourseFieldList::fieldsForDetail() 
                : CourseFieldList::fieldsForList();

        $data = [];

        // only include fields that are present on the model instance
        foreach ($fields as $field) {
            if (array_key_exists($field, $this->resource->getAttributes())) {
                $value = $this->{$field};

                // cast booleans explicitly
                if (in_array($field, ['is_featured', 'is_free'], true)) {
                    $value = (bool) $value;
                }

                $data[$field] = $value;
            }
        }

        // include relations only when loaded
        if ($this->relationLoaded('instructor')) {
            $instructor = $this->instructor;
            $data['instructor'] = $instructor ? [
                'id' => $instructor->id ?? null,
                'full_name' => $instructor->full_name ?? null,
                'phone' => $instructor->phone ?? null,
                'avatar' => $instructor->avatar ?? null,
                'field' => $instructor->field ?? null,
                'bio' => $instructor->bio ?? null,
                'gender' => $instructor->gender ?? null,
                'status' => $instructor->status ?? null,
            ] : null;
        }

        if ($this->relationLoaded('category')) {
            $category = $this->category;
            $data['category'] = $category ? [
                'id' => $category->id ?? null,
                'name' => $category->name ?? null,
                'slug' => $category->slug ?? null,
                'description' => $category->description ?? null,
                'icon' => $category->icon ?? null,
                'image' => $category->image ?? null,
                'parent_id' => $category->parent_id ?? null,
                'sort_order' => $category->sort_order ?? null,
                'status' => $category->status ?? null,
            ] : null;
        }

        return $data;
    }
}
