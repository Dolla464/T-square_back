<?php

namespace App\Services\User;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Attendance\AttendanceSessionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class LessonVisibilityService
{
    private const UNLOCK_HOURS_AFTER_SESSION_END = 1;

    public function __construct(
        private readonly AttendanceSessionService $sessionService,
    ) {}

    /**
     * @return int[] Visible lesson IDs keyed as a flat list for membership checks.
     */
    private function buildLessonAvailabilityMap(
        Enrollment $enrollment,
        Course $course,
        Collection $allLessons,
    ): array {
        if ($this->isOnlineAttendance($enrollment->attendance_type)) {
            return $allLessons->pluck('id')->all();
        }

        if (! $enrollment->group_id) {
            return [];
        }

        $orderedLessons = $allLessons->sortBy('sort_order')->values();
        $sessions = $this->loadOrderedGroupSessions((int) $enrollment->group_id);
        $now = Carbon::now();
        $visibleLessonIds = [];

        foreach ($sessions as $index => $session) {
            $lesson = $orderedLessons->get($index);

            if ($lesson === null) {
                break;
            }

            if ($this->isSessionUnlockedForLesson($session, $now)) {
                $visibleLessonIds[] = $lesson->id;
            }
        }

        return $visibleLessonIds;
    }

    public function resolveVisibleLessons(
        Enrollment $enrollment,
        Course $course,
        Collection $allLessons,
    ): Collection {
        $visibleIds = $this->buildLessonAvailabilityMap($enrollment, $course, $allLessons);

        if ($visibleIds === []) {
            return $allLessons->whereIn('id', [])->values();
        }

        $visibleIdLookup = array_fill_keys($visibleIds, true);

        return $allLessons
            ->filter(fn (Lesson $lesson) => isset($visibleIdLookup[$lesson->id]))
            ->values();
    }

    public function isLessonAvailable(
        Enrollment $enrollment,
        Course $course,
        Lesson $lesson,
    ): bool {
        if ((int) $enrollment->course_id !== (int) $course->id) {
            return false;
        }

        if ((int) $lesson->course_id !== (int) $course->id) {
            return false;
        }

        $allLessons = $course->relationLoaded('lessons')
            ? $course->lessons->where('is_active', true)->values()
            : $course->lessons()->active()->ordered()->get();

        $visibleIds = $this->buildLessonAvailabilityMap($enrollment, $course, $allLessons);

        return in_array($lesson->id, $visibleIds, true);
    }

    private function isOnlineAttendance(?string $attendanceType): bool
    {
        return $attendanceType === 'online';
    }

    /**
     * @return Collection<int, AttendanceSession>
     */
    private function loadOrderedGroupSessions(int $groupId): Collection
    {
        return AttendanceSession::query()
            ->where('learning_group_id', $groupId)
            ->with('schedule')
            ->get()
            ->sortBy(fn (AttendanceSession $session) => $this->sessionService->getEffectiveDateTimeRange($session)['start'])
            ->values();
    }

    private function isSessionUnlockedForLesson(AttendanceSession $session, Carbon $now): bool
    {
        if ($session->status === 'cancelled' && ! $session->release_lesson_on_cancel) {
            return false;
        }

        $range = $this->sessionService->getEffectiveDateTimeRange($session);
        $unlockAt = $range['end']->copy()->addHours(self::UNLOCK_HOURS_AFTER_SESSION_END);

        return $now->greaterThanOrEqualTo($unlockAt);
    }
}
