<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Models\LearningGroup;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateWeeklySessions extends Command
{
    protected $signature   = 'attendance:generate-weekly';
    protected $description = 'Generate attendance sessions for the upcoming week for all active groups if they do not already exist.';

    public function handle(): void
    {
        // Our day mapping: 0=Sat,1=Sun,2=Mon,3=Tue,4=Wed,5=Thu,6=Fri → Carbon dayOfWeek
        $dayMap = [0 => 6, 1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5];

        $weekStart = Carbon::today();
        $weekEnd   = $weekStart->copy()->addDays(6);

        $groups = LearningGroup::where('status', 'active')
            ->where('end_date', '>=', $weekStart)
            ->with('schedules')
            ->get();

        $generated = 0;

        foreach ($groups as $group) {
            $rangeStart = $weekStart->copy()->max($group->start_date);
            $rangeEnd   = $weekEnd->copy()->min($group->end_date);

            if ($rangeStart->gt($rangeEnd)) {
                continue;
            }

            $current = $rangeStart->copy();

            while ($current->lte($rangeEnd)) {
                foreach ($group->schedules as $schedule) {
                    $carbonDay = $dayMap[$schedule->day_of_week] ?? null;

                    if ($carbonDay !== null && $current->dayOfWeek === $carbonDay) {
                        $exists = AttendanceSession::where('learning_group_id', $group->id)
                            ->where('schedule_id', $schedule->id)
                            ->whereDate('session_date', $current->toDateString())
                            ->exists();

                        if (!$exists) {
                            AttendanceSession::create([
                                'learning_group_id' => $group->id,
                                'schedule_id'       => $schedule->id,
                                'session_date'      => $current->copy(),
                                'status'            => 'upcoming',
                            ]);
                            $generated++;
                        }
                    }
                }
                $current->addDay();
            }
        }

        $this->info("Generated {$generated} new attendance session(s) for the upcoming week.");
    }
}
