<?php

namespace App\Http\Resources\User\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $student = $this->whenLoaded('student');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_verified' => (bool)($this->email_verified_at),
            'student' => $student ? [
                'full_name' => $student->full_name,
                'avatar' => $student->avatar
                    ? asset('storage/' . $student->avatar)
                    : asset('assets/default-student.png'),
                'gender' => $student->gender ?? 'not_set',
                'phone' => $student->phone ?? '',
            ] : null,
        ];
    }
}
