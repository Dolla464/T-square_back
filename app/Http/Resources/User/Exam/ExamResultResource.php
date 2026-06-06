<?php

namespace App\Http\Resources\User\Exam;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $passingMark = $this->exam->passing_mark;
        $studentScore = $this->score;
        $canDownloadCertificate = ($this->status === 'passed' && $this->exam->is_final);
        $enrollmentId = $canDownloadCertificate
            ? Enrollment::where('student_id', '=', $this->student_id, 'and')
                ->where('course_id', '=', $this->exam->course_id, 'and')
                ->value('id')
            : null;

        return [
            'attempt_id' => $this->id,
            'exam_id' => $this->exam_id,
            'exam_title' => $this->exam->title,
            'course_id' => $this->exam->course_id,
            'course_name' => $this->exam->course->title,
            'score' => $studentScore,
            'total_marks' => $this->exam->total_marks,
            'passing_mark' => $passingMark,
            'status' => $this->status, // completed / timed_out
            'is_passed' => $studentScore >= $passingMark, // المنطق هنا
            'can_download_certificate' => $canDownloadCertificate,
            'enrollment_id' => $enrollmentId,
            'finished_at' => $this->finished_at?->format('Y-m-d H:i'),
        ];
    }
}
