<?php

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\GroupExamActivation;
use App\Models\LearningGroup;

class GroupExamActivationService
{
    public function getExamsWithActivationStatus(LearningGroup $group): array
    {
        $group->load('course:id,title');

        $activatedExamIds = GroupExamActivation::query()
            ->where('learning_group_id', $group->id)
            ->pluck('exam_id')
            ->flip();

        return Exam::query()
            ->where('course_id', $group->course_id)
            ->orderBy('title')
            ->get(['id', 'title', 'total_marks', 'passing_mark', 'max_attempts', 'is_active'])
            ->map(fn (Exam $exam) => [
                'id'                     => $exam->id,
                'title'                  => $exam->title,
                'total_marks'            => $exam->total_marks,
                'passing_mark'           => $exam->passing_mark,
                'max_attempts'           => $exam->max_attempts,
                'is_active'              => (bool) $exam->is_active,
                'is_activated_for_group' => $activatedExamIds->has($exam->id),
            ])
            ->values()
            ->all();
    }

    public function toggleActivation(
        LearningGroup $group,
        Exam $exam,
        int $instructorId,
        bool $isActivated
    ): array {
        if ((int) $exam->course_id !== (int) $group->course_id) {
            throw new \InvalidArgumentException('Exam not found for this group.');
        }

        if ($isActivated && ! $exam->is_active) {
            abort(422, 'Cannot activate exam for group while exam is globally inactive.');
        }

        if ($isActivated) {
            GroupExamActivation::updateOrCreate(
                [
                    'exam_id'           => $exam->id,
                    'learning_group_id' => $group->id,
                ],
                [
                    'activated_by' => $instructorId,
                    'activated_at' => now(),
                ]
            );
        } else {
            GroupExamActivation::query()
                ->where('exam_id', $exam->id)
                ->where('learning_group_id', $group->id)
                ->delete();
        }

        return [
            'exam_id'                => $exam->id,
            'learning_group_id'      => $group->id,
            'is_activated_for_group' => $isActivated,
        ];
    }
}
