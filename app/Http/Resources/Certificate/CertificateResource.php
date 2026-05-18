<?php

namespace App\Http\Resources\Certificate;

use App\Models\Certificate;
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
        $certificate = null;
        if ($this->is_completed) {
            $certificate = Certificate::where('student_id', $this->student_id)
                ->where('course_id', $this->course_id)
                ->first();
        }

        return [
            'id' => $this->id,
            // استخدم optional عشان لو الطالب ممسوح أو مش موجود م يضربش Error
            'student_name' => optional($this->student)->full_name ?? 'Student Not Found',
            'course_title' => optional($this->course)->title ?? 'Course Not Found',
            'is_completed' => (bool) $this->is_completed,
            'certificate_url' => $this->is_completed
                ? route('student.certificates.download', ['enrollment' => $this->id])
                : null,
            'certificate_num' => $certificate ? $certificate->certificate_num : null,
            'issued_at' => $certificate ? $certificate->issued_at : null,
            'completed_at' => $this->completed_at,
        ];
    }
}
