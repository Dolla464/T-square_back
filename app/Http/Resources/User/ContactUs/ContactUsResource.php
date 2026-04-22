<?php

namespace App\Http\Resources\User\ContactUs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactUsResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'learning_track' => $this->learning_track,
            'message' => $this->message,
            'submitted_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
