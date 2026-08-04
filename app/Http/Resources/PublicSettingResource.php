<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->resolvePublicValue(),
        ];
    }

    private function resolvePublicValue(): mixed
    {
        return match ($this->type) {
            'image' => asset('site-media/'.$this->value),
            'json' => json_decode($this->value, true),
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            default => $this->value,
        };
    }
}
