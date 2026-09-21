<?php

namespace App\Http\Resources\User\Exam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptIntegrityEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_type' => $this->event_type,
            'client_at' => $this->client_at?->toIso8601String(),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
}
