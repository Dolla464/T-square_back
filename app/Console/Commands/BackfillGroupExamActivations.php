<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\GroupExamActivation;
use App\Models\LearningGroup;
use Illuminate\Console\Command;

class BackfillGroupExamActivations extends Command
{
    protected $signature = 'exams:backfill-group-activations {--dry-run : Preview without writing}';

    protected $description = 'Activate all globally active exams for every learning group in the same course.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;

        $groups = LearningGroup::query()->get(['id', 'course_id']);

        foreach ($groups as $group) {
            $exams = Exam::query()
                ->where('course_id', $group->course_id)
                ->where('is_active', true)
                ->get(['id']);

            foreach ($exams as $exam) {
                $exists = GroupExamActivation::query()
                    ->where('exam_id', $exam->id)
                    ->where('learning_group_id', $group->id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                if (! $dryRun) {
                    GroupExamActivation::create([
                        'exam_id'           => $exam->id,
                        'learning_group_id' => $group->id,
                        'activated_by'      => null,
                        'activated_at'      => now(),
                    ]);
                }

                $created++;
            }
        }

        $action = $dryRun ? 'Would create' : 'Created';
        $this->info("{$action} {$created} activation(s). Skipped {$skipped} existing.");

        return self::SUCCESS;
    }
}
