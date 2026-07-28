<?php

namespace App\Http\Resources\User\Exam;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attemptMaxMarks = (float) ($this->relationLoaded('questions')
            ? $this->questions->sum('marks')
            : $this->questions()->sum('marks'));

        $examTotalMarks = (float) $this->exam->total_marks;
        $attemptPassingMark = $examTotalMarks > 0
            ? round(($this->exam->passing_mark / $examTotalMarks) * $attemptMaxMarks, 2)
            : 0.0;

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
            'total_marks' => $attemptMaxMarks,
            'attempt_max_marks' => $attemptMaxMarks,
            'passing_mark' => $attemptPassingMark,
            'attempt_passing_mark' => $attemptPassingMark,
            'status' => $this->status,
            'is_passed' => $studentScore >= $attemptPassingMark,
            'can_download_certificate' => $canDownloadCertificate,
            'enrollment_id' => $enrollmentId,
            'finished_at' => $this->finished_at?->format('Y-m-d H:i'),
        ];
    }
}
