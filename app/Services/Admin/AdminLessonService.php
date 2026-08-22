<?php

namespace App\Services\Admin;

use App\Models\Course;
use App\Models\Lesson;
use App\Services\Google\GoogleDriveUrlParser;
use App\Services\Google\GoogleStorageAccountService;
use Illuminate\Database\Eloquent\Collection;

class AdminLessonService
{
    public function __construct(
        private readonly GoogleDriveUrlParser $urlParser,
        private readonly GoogleStorageAccountService $storageAccountService,
    ) {}

    public function listForCourse(int $courseId): Collection
    {
        return Lesson::query()
            ->where('course_id', $courseId)
            ->ordered()
            ->get();
    }

    public function create(Course $course, array $data): Lesson
    {
        $payload = $this->buildPayload($course, $data);

        $lesson = Lesson::create($payload);

        return $this->validateDriveAccess($course, $lesson);
    }

    public function update(Course $course, Lesson $lesson, array $data): Lesson
    {
        if ($lesson->course_id !== $course->id) {
            abort(404);
        }

        $payload = $this->buildPayload($course, $data, $lesson);
        $lesson->update($payload);

        return $this->validateDriveAccess($course, $lesson->fresh());
    }

    public function delete(Course $course, Lesson $lesson): void
    {
        if ($lesson->course_id !== $course->id) {
            abort(404);
        }

        $lesson->delete();
    }

    private function buildPayload(Course $course, array $data, ?Lesson $existing = null): array
    {
        $source = $data['video_source_type'] ?? $existing?->video_source_type ?? Lesson::VIDEO_SOURCE_NONE;

        $payload = [
            'course_id' => $course->id,
            'title' => $data['title'] ?? $existing?->title,
            'description' => $data['description'] ?? $existing?->description,
            'sort_order' => $data['sort_order'] ?? $existing?->sort_order ?? 0,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : ($existing?->is_active ?? true),
            'duration_seconds' => $data['duration_seconds'] ?? $existing?->duration_seconds,
            'video_source_type' => $source === Lesson::VIDEO_SOURCE_NONE ? null : $source,
            'google_drive_file_id' => null,
        ];

        if ($source === Lesson::VIDEO_SOURCE_GOOGLE_DRIVE && ! empty($data['google_drive_url'])) {
            $parsed = $this->urlParser->parse($data['google_drive_url']);
            $payload['google_drive_file_id'] = $parsed['file_id'];
        } elseif ($source === Lesson::VIDEO_SOURCE_GOOGLE_DRIVE && $existing?->google_drive_file_id) {
            $payload['google_drive_file_id'] = $existing->google_drive_file_id;
        }

        return $payload;
    }

    private function validateDriveAccess(Course $course, Lesson $lesson): Lesson
    {
        if (! $lesson->hasGoogleDriveVideo() || ! $course->google_storage_account_id) {
            return $lesson;
        }

        $course->loadMissing('googleStorageAccount');
        $account = $course->googleStorageAccount;

        if (! $account) {
            $lesson->update([
                'drive_validation_status' => 'missing_account',
                'drive_validation_message' => 'No Google storage account assigned to this course.',
            ]);

            return $lesson->fresh();
        }

        $result = $this->storageAccountService->validateFileAccess(
            $account,
            $lesson->google_drive_file_id
        );

        $lesson->update([
            'drive_validation_status' => $result['status'],
            'drive_validation_message' => $result['message'],
        ]);

        return $lesson->fresh();
    }
}
