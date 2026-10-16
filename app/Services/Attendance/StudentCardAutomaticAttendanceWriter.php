<?php

namespace App\Services\Attendance;

use App\Events\StudentScanned;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StudentCardAutomaticAttendanceWriter
{
    /**
     * Record attendance from a permanent card scan without overwriting late or excused statuses.
     */
    public function recordFromPermanentCard(AttendanceSession $session, Student $student): AttendanceRecord
    {
        return DB::transaction(function () use ($session, $student) {
            $record = AttendanceRecord::query()
                ->where('session_id', $session->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            $now = Carbon::now();

            if ($record !== null && in_array($record->status, ['late', 'excused'], true)) {
                return $record;
            }

            if ($record !== null && $record->status === 'present') {
                return $record;
            }

            $record = AttendanceRecord::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'student_id' => $student->id,
                ],
                [
                    'status' => 'present',
                    'marked_by' => 'student_card',
                    'marked_at' => $now,
                ]
            );

            $record->load(['student', 'session.learningGroup']);
            broadcast(new StudentScanned($record))->toOthers();

            return $record;
        });
    }
}
