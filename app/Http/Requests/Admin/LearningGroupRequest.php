<?php

namespace App\Http\Requests\Admin;

use App\Models\LearningGroupSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class LearningGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('learning_group') !== null;

        // Historical create allows past start_date; normal create requires today or later.
        $isHistorical = $isUpdate
            ? false
            : filter_var($this->input('is_historical'), FILTER_VALIDATE_BOOLEAN);

        $startDateRule = match (true) {
            $isUpdate      => 'required|date',
            $isHistorical  => 'required|date|before_or_equal:today',
            default        => 'required|date|after_or_equal:today',
        };

        // Schedules are optional on update when only other fields change (future-only sync).
        $schedulesRule = $isUpdate
            ? 'nullable|array|min:1'
            : 'required|array|min:1';

        return [
            'group_name'   => 'required|string|max:255',
            'course_id'    => 'required|exists:courses,id',
            'course_instructor_id' => 'required|exists:course_instructor,id',
            'instructor_id' => 'sometimes|nullable|exists:instructors,id',
            'start_date'    => $startDateRule,
            'is_historical' => 'nullable|boolean',
            'status'        => 'nullable|in:active,completed,cancelled',

            'schedules'               => $schedulesRule,
            'schedules.*.day_of_week' => 'required|integer|between:0,6',
            'schedules.*.start_time'  => 'required|date_format:H:i',
            'schedules.*.end_time'    => 'required|date_format:H:i|after:schedules.*.start_time',
            'schedules.*.room'        => 'nullable|string|max:255',

            'student_ids'        => 'nullable|array',
            'student_ids.*'      => 'integer|exists:students,id',
            'student_statuses'   => 'nullable|array',
            'student_statuses.*' => 'boolean',
        ];
    }

    /**
     * Hook into the validator to add cross-field / DB checks after base rules pass.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->checkHistoricalStartDate($v);
            $this->checkCourseInstructorBelongsToCourse($v);
            $this->checkInstructorScheduleOverlap($v);
        });
    }

    /**
     * Reject contradictory is_historical / start_date combinations on create.
     */
    private function checkHistoricalStartDate(Validator $v): void
    {
        if ($this->route('learning_group') !== null || $v->errors()->isNotEmpty()) {
            return;
        }

        $startDate = $this->input('start_date');
        if (! $startDate) {
            return;
        }

        $today = now()->toDateString();
        $isHistorical = filter_var($this->input('is_historical'), FILTER_VALIDATE_BOOLEAN);

        if ($isHistorical && $startDate > $today) {
            $v->errors()->add(
                'start_date',
                'Historical groups must have a start date on or before today.'
            );
        }

        if (! $isHistorical && $startDate < $today) {
            $v->errors()->add(
                'start_date',
                'Start date must be today or a future date.'
            );
        }
    }

    private function checkCourseInstructorBelongsToCourse(Validator $v): void
    {
        if ($v->errors()->isNotEmpty()) {
            return;
        }

        $courseId = $this->input('course_id');
        $courseInstructorId = $this->input('course_instructor_id');

        if (! $courseId || ! $courseInstructorId) {
            return;
        }

        $belongs = DB::table('course_instructor')
            ->where('id', $courseInstructorId)
            ->where('course_id', $courseId)
            ->exists();

        if (! $belongs) {
            $v->errors()->add(
                'course_instructor_id',
                'The selected instructor is not assigned to this course.'
            );
        }
    }

    /**
     * Ensure the instructor has no overlapping schedule on the same day_of_week
     * in any other learning group (excluding the group being updated).
     */
    private function checkInstructorScheduleOverlap(Validator $v): void
    {
        if ($v->errors()->isNotEmpty()) {
            return;
        }

        $courseInstructorId = $this->input('course_instructor_id');
        $schedules    = $this->input('schedules', []);

        // When updating, exclude the current group's schedules from the overlap check.
        // apiResource generates {learning_group} (snake_case), not {learningGroup}.
        $routeModel     = $this->route('learning_group');
        $excludeGroupId = $routeModel instanceof \App\Models\LearningGroup
            ? $routeModel->id
            : null;

        foreach ($schedules as $index => $schedule) {
            $dayOfWeek = $schedule['day_of_week'] ?? null;
            $startTime = $schedule['start_time'] ?? null;
            $endTime   = $schedule['end_time']   ?? null;

            if (is_null($dayOfWeek) || is_null($startTime) || is_null($endTime)) {
                continue;
            }

            $query = DB::table('learning_group_schedules as lgs')
                ->join('learning_groups as lg', 'lg.id', '=', 'lgs.learning_group_id')
                ->where('lg.course_instructor_id', $courseInstructorId)
                ->where('lgs.day_of_week', $dayOfWeek)
                ->where(function ($q) use ($startTime, $endTime) {
                    // Overlap condition: new interval overlaps existing interval
                    $q->where('lgs.start_time', '<', $endTime)
                      ->where('lgs.end_time', '>', $startTime);
                });

            if ($excludeGroupId) {
                $query->where('lgs.learning_group_id', '!=', $excludeGroupId);
            }

            if ($query->exists()) {
                $dayNames = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                $dayName  = $dayNames[$dayOfWeek] ?? "day {$dayOfWeek}";
                $v->errors()->add(
                    "schedules.{$index}.day_of_week",
                    "The instructor already has an overlapping schedule on {$dayName} between {$startTime} and {$endTime}."
                );
            }
        }
    }

    public function messages(): array
    {
        return [
            'schedules.required'               => 'At least one schedule day is required.',
            'schedules.min'                    => 'At least one schedule day is required.',
            'schedules.*.day_of_week.required' => 'Each schedule must specify a day of the week.',
            'schedules.*.day_of_week.between'  => 'Day of week must be between 0 (Saturday) and 6 (Friday).',
            'schedules.*.start_time.required'  => 'Each schedule must have a start time.',
            'schedules.*.start_time.date_format' => 'Start time must be in H:i format (e.g. 09:00).',
            'schedules.*.end_time.required'    => 'Each schedule must have an end time.',
            'schedules.*.end_time.date_format' => 'End time must be in H:i format (e.g. 11:00).',
            'schedules.*.end_time.after'       => 'End time must be after start time.',
            'start_date.after_or_equal'        => 'Start date must be today or a future date.',
            'start_date.before_or_equal'       => 'Historical groups must have a start date on or before today.',
        ];
    }
}
