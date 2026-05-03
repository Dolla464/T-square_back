<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminInstructorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'full_name' => $this->full_name,
            'email' => $this->user?->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar ? asset('storage/' . $this->getRawOriginal('avatar')) : null,
            'field' => $this->field,
            'bio' => $this->bio,
            'gender' => $this->gender,
            'insta_url' => $this->insta_url,
            'linkedin_url' => $this->linkedin_url,
            'facebook_url' => $this->facebook_url,
            'status' => $this->status,
            'avg_rating' => $this->avg_rating,
            'reviews_count' => $this->reviews_count,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
