<?php

namespace App\Http\Resources\Certificate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
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
            'student_name' => $this->student->full_name,
            'course_title' => $this->course->title,
            'is_completed' => (bool) $this->is_completed,
            'certificate_url' => $this->is_completed
                ? route('certificate.download', ['enrollment' => $this->id])
                : null,
            'completed_at' => $this->completed_at,
        ];
    }
}
