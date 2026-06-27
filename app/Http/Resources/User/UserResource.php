<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->whenLoaded('roles', fn() => $this->roles->first()?->name),
            'phone' => $this->whenLoaded('student', fn() => $this->student?->phone),
            'is_verified' => ! is_null($this->email_verified_at),
        ];
    }
}
