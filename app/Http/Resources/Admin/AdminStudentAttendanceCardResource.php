<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminStudentAttendanceCardResource extends JsonResource
{
    /**
     * Safe card metadata for admin UI (never includes raw token or token_hash).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'token_suffix' => $this->token_suffix,
            'issued_at' => $this->issued_at?->toDateTimeString(),
            'issued_by_user_id' => $this->issued_by_user_id,
            'revoked_at' => $this->revoked_at?->toDateTimeString(),
            'revoked_by_user_id' => $this->revoked_by_user_id,
            'revocation_reason' => $this->revocation_reason,
        ];
    }
}
