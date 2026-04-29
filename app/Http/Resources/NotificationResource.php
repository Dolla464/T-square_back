<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, // الـ ID ده هو اللي الفرونت هيبعتهولنا عشان نعمل Mark as Read
            'title' => $this->data['title'] ?? '',
            'message' => $this->data['message'] ?? '',
            'url' => $this->data['url'] ?? '',
            'icon' => $this->data['icon'] ?? '',
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at->diffForHumans(), // هترجع "منذ ساعتين" مثلاً
        ];
    }
}
