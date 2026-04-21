<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'image' => $this->avatar
                                ? asset('storage/'.$this->avatar)
                                : null,
            'fullname' => $this->full_name,
            'field' => $this->field,
            'email' => $this->whenLoaded('user', fn () => $this->user->email),
            'insta_url' => $this->insta_url,
            'linkedin_url' => $this->linkedin_url,
            'facebook_url' => $this->facebook_url,
        ];
    }
}
