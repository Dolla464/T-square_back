<?php

namespace App\Http\Resources\Admin\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscoveryMediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Since the data is an array of direct URLs, the $this here represents the array itself
        return [
            'images' => $this->resource,
            'total_count' => count($this->resource),
        ];
    }
}
