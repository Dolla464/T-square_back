<?php

namespace App\Services\Attendance;

use App\Models\AttendanceSession;
use App\Models\LearningGroup;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttendanceSessionService
{
    public function assertSessionBelongsToGroup(AttendanceSession $session, LearningGroup $group): bool
    {
        return (int) $session->learning_group_id === (int) $group->id;
    }

    public function assertAdminCanMarkHistoricalSession(AttendanceSession $session): void
    {
        if ($session->status === 'cancelled') {
            throw new HttpException(422, 'Cannot mark attendance for cancelled sessions.');
        }

        $times       = $this->getEffectiveTimes($session);
        $sessionDate = $times['session_date'] ?? $session->session_date->format('Y-m-d');
        $isPast      = Carbon::parse($sessionDate)->startOfDay()->lt(Carbon::today());
        $isCompleted = $session->status === 'completed';

        if (! $isCompleted && ! $isPast) {
            throw new HttpException(422, 'Attendance can only be marked for completed or past sessions.');
        }
    }

    public function getSessionDetails(AttendanceSession $session): array
    {
        $session->load([
            'schedule',
            'learningGroup:id,group_name,course_id,course_instructor_id',
            'learningGroup.course:id,title',
            'attendanceRecords',
        ]);

        $students = $session->learningGroup->students()->get();

        $studentList = $students->map(function ($student) use ($session) {
            $record = $session->attendanceRecords
                ->where('student_id', $student->id)
                ->first();

            return [
                'student_id' => $student->id,
                'full_name'  => $student->full_name ?? $student->user?->name ?? 'Unknown',
                'email'      => $student->email ?? $student->user?->email ?? null,
                'avatar'     => $student->avatar ?? $student->user?->avatar ?? null,
                'status'     => $record?->status ?? 'not_marked',
                'marked_at'  => $record?->marked_at?->toDateTimeString(),
                'marked_by'  => $record?->marked_by,
                'notes'      => $record?->notes,
            ];
        });

        $presentCount = $studentList->whereIn('status', ['present', 'late'])->count();
        $times        = $this->getEffectiveTimes($session);

        return [
            'session_id'   => $session->id,
            'group_name'   => $session->learningGroup->group_name,
            'course_title' => $session->learningGroup->course->title ?? null,
            'session_date' => $session->session_date->format('Y-m-d'),
            'start_time'   => $times['start_time'],
            'end_time'     => $times['end_time'],
            'room'         => $session->schedule->room ?? null,
            'status'       => $session->status,
            'qr_code'      => $session->qr_code,
            'attendance'   => [
                'total'   => $studentList->count(),
                'present' => $presentCount,
                'absent'  => $studentList->count() - $presentCount,
            ],
            'students'     => $studentList->values()->all(),
        ];
    }

    public function getEffectiveTimes(AttendanceSession $session): array
    {
        $startRaw = $session->override_start_time ?? $session->schedule?->start_time;
        $endRaw   = $session->override_end_time ?? $session->schedule?->end_time;

        return [
            'start_time'   => $this->formatTime($startRaw),
            'end_time'     => $this->formatTime($endRaw),
            'session_date' => ($session->override_date ?? $session->session_date)?->format('Y-m-d'),
        ];
    }

    public function getEffectiveDateTimeRange(AttendanceSession $session): array
    {
        $session->loadMissing('schedule');
        $times = $this->getEffectiveTimes($session);
        $date  = $times['session_date'] ?? $session->session_date->format('Y-m-d');

        return [
            'session_date' => $date,
            'start'        => Carbon::parse("{$date} {$times['start_time']}"),
            'end'          => Carbon::parse("{$date} {$times['end_time']}"),
        ];
    }

    private function formatTime($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value) && method_exists($value, 'format')) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
