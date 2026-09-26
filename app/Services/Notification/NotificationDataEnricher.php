<?php

namespace App\Services\Notification;

use App\Models\Exam;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NotificationDataEnricher
{
    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     */
    public function enrich(Collection $notifications): void
    {
        $this->enrichExamResults($notifications);
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     */
    private function enrichExamResults(Collection $notifications): void
    {
        $examIds = $notifications
            ->filter(fn ($notification) => $this->needsExamResultEnrichment($notification->data))
            ->pluck('data.exam_id')
            ->filter()
            ->unique()
            ->values();

        if ($examIds->isEmpty()) {
            return;
        }

        $exams = Exam::query()
            ->with('course:id,title')
            ->whereIn('id', $examIds)
            ->get()
            ->keyBy('id');

        foreach ($notifications as $notification) {
            $data = $notification->data;

            if (! $this->needsExamResultEnrichment($data)) {
                continue;
            }

            $examId = $data['exam_id'] ?? null;
            $exam = $examId ? $exams->get($examId) : null;

            if (! $exam) {
                continue;
            }

            $examTitle = $exam->title ?? 'Exam';
            $courseTitle = $exam->course?->title ?? 'Course';

            if (empty($data['exam_title'])) {
                $data['exam_title'] = $examTitle;
            }

            if (empty($data['course_title'])) {
                $data['course_title'] = $courseTitle;
            }

            if (empty($data['course_id']) && $exam->course_id) {
                $data['course_id'] = $exam->course_id;
            }

            $message = trim((string) ($data['message'] ?? ''));
            if ($message === 'You passed your exam attempt.' || $message === 'You failed your exam attempt.') {
                $result = ($data['status'] ?? '') === 'passed' ? 'passed' : 'failed';
                $data['message'] = "You {$result} the exam \"{$data['exam_title']}\" in course \"{$data['course_title']}\".";
            }

            $notification->data = $data;
        }
    }

    private function needsExamResultEnrichment(array $data): bool
    {
        if (($data['type'] ?? null) !== 'exam_result') {
            return false;
        }

        return empty($data['exam_title']) || empty($data['course_title']);
    }
}
