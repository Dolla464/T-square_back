<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'email' => $this->whenLoaded('user', fn() => $this->user->email),
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'enrollment_number' => $this->enrollment_number,
            'group_id' => $this->group_id,
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : asset('assets/default-student.png'),
            'gender' => $this->gender,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
