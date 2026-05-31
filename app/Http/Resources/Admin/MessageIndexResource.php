<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageIndexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Only exposes the four summary fields suitable for listing views.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'title'   => $this->title,
            'content' => $this->content,
        ];
    }
}
