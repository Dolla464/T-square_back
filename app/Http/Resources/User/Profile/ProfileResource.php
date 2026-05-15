<?php

namespace App\Http\Resources\User\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

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
            'is_verified' => (bool) ($this->email_verified_at),
            'student'     => $this->whenLoaded('student', function () {
                return [
                    'full_name' => $this->student->full_name ?? $this->name,
                    'avatar'    => $this->student->avatar
                        ? asset('storage/' . $this->student->avatar)
                        : asset('assets/default-student.png'),
                    'gender'    => $this->student->gender ?? 'not_set',
                    'phone'     => $this->student->phone ?? '',
                ];
            }),
        ];
    }
}
