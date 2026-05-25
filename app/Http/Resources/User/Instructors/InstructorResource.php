<?php

namespace App\Http\Resources\User\Instructors;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'image' => $this->avatar ?? null,
            'fullname' => $this->full_name,
            'field' => $this->field,
            'email' => $this->user?->email ?? null,
            'insta_url' => $this->insta_url,
            'linkedin_url' => $this->linkedin_url,
            'facebook_url' => $this->facebook_url,
        ];
    }
}
