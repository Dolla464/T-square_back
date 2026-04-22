<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolutionResource extends JsonResource
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
            'name' => $this->title,
            'description' => $this->description,
            'tags' => $this->tags->map(function ($tag) {
                return [
                    'tag_id' => $tag->id,
                    'tag_name' => $tag->name,
                ];
            })->values(),
        ];
    }
}
