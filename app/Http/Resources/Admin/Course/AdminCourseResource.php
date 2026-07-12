<?php

namespace App\Http\Resources\Admin\Course;

use App\Support\CourseInstructorSync;
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

        $data['is_trashed'] = $this->trashed(); // Returns true if in the trash
        $data['deleted_at'] = $this->deleted_at ? $this->deleted_at->format('Y-m-d H:i:s') : null;

        // include relations only when loaded
        if ($this->relationLoaded('instructors')) {
            $data['instructors'] = CourseInstructorSync::formatInstructorsCollection($this->resource);
            $data['instructor_ids'] = collect($data['instructors'])->pluck('id')->values()->all();
        }

        /** @deprecated Use instructors[]. Will be removed in v2. */
        if ($this->relationLoaded('instructors')) {
            $data['instructor'] = $data['instructors'][0] ?? null;
        } elseif ($this->relationLoaded('instructor')) {
            $instructor = $this->instructor;
            $data['instructor'] = $instructor ? [
                'id' => $instructor->id ?? null,
                'full_name' => $instructor->full_name ?? null,
            ] : null;
        }

        if ($this->relationLoaded('category')) {
            $category = $this->category;
            $data['category'] = $category ? [
                'id' => $category->id ?? null,
                'name' => $category->name ?? null,
                'slug' => $category->slug ?? null,
                'parent_id' => $category->parent_id ?? null,
            ] : null;
        }

        if ($this->relationLoaded('tags')) {
            $data['tags'] = $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values();
        }

        if ($this->relationLoaded('previews')) {
            $data['previews'] = $this->previews->map(fn ($preview) => [
                'id' => $preview->id,
                'title' => $preview->title,
                'video_url' => $preview->video_url,
                'description' => $preview->description,
                'video_provider' => $preview->video_provider,
                'duration_seconds' => $preview->duration_seconds,
                'sort_order' => $preview->sort_order,
            ])->values();
        }

        if ($this->relationLoaded('learnings')) {
            // Use ?? [] to ensure that in case it is null, it is treated as an empty array
            $data['learnings'] = collect($this->learnings ?? [])->map(fn ($learning) => [
                'id' => $learning->id,
                'title' => $learning->title,
            ])->values();
        }

        return $data;
    }
}
