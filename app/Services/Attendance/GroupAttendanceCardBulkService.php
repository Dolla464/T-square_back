<?php

namespace App\Services\Attendance;

use App\Http\Resources\Admin\AdminStudentAttendanceCardResource;
use App\Models\GroupAttendanceCardBulkOperation;
use App\Models\GroupAttendanceCardBulkOperationStudent;
use App\Models\LearningGroup;
use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Models\User;
use App\Services\Attendance\DTO\IssuedStudentAttendanceCard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class GroupAttendanceCardBulkService
{
    private const GROUP_LOCK_SECONDS = 120;

    public function __construct(
        private GroupAttendanceCardEligibilityService $eligibilityService,
        private StudentAttendanceCardLifecycleService $lifecycleService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function processBatch(LearningGroup $group, User $admin, array $payload): array
    {
        $this->assertAdmin($admin);

        $clientOperationId = (string) $payload['client_operation_id'];
        $mode = (string) $payload['mode'];
        $confirmReissue = (bool) ($payload['confirm_reissue'] ?? false);
        $revocationReason = isset($payload['revocation_reason']) ? (string) $payload['revocation_reason'] : null;
        $fromSequence = (int) $payload['batch_cursor']['from_sequence'];
        $toSequence = (int) $payload['batch_cursor']['to_sequence'];

        if ($toSequence < $fromSequence) {
            throw new GroupAttendanceCardBulkException(
                GroupAttendanceCardBulkResultCode::FAILED_VALIDATION,
                'Invalid batch cursor range.',
                422,
            );
        }

        $batchSize = $toSequence - $fromSequence + 1;
        if ($batchSize > GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX) {
            throw new GroupAttendanceCardBulkException(
                GroupAttendanceCardBulkResultCode::BATCH_SIZE_EXCEEDED,
                'Batch size exceeds the maximum allowed.',
                422,
            );
        }

        if ($fromSequence % GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX !== 0) {
            throw new GroupAttendanceCardBulkException(
                GroupAttendanceCardBulkResultCode::CURSOR_OUT_OF_ORDER,
                'Batch must start on a batch boundary.',
                409,
            );
        }

        $batchIndex = (int) ($fromSequence / GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX);
        $fingerprint = $this->buildFingerprint($group, $admin, $mode, $confirmReissue, $revocationReason);

        $lock = Cache::lock($this->groupLockKey($group->id), self::GROUP_LOCK_SECONDS);

        if (! $lock->get()) {
            throw new GroupAttendanceCardBulkException(
                GroupAttendanceCardBulkResultCode::GROUP_LOCK_UNAVAILABLE,
                'Another bulk attendance card operation is in progress for this group.',
                409,
            );
        }

        try {
            return $this->processBatchWithinGroupLock(
                $group,
                $admin,
                $clientOperationId,
                $mode,
                $confirmReissue,
                $revocationReason,
                $fingerprint,
                $batchIndex,
                $fromSequence,
                $toSequence,
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function operationStatus(LearningGroup $group, string $clientOperationId): array
    {
        $operation = GroupAttendanceCardBulkOperation::query()
            ->where('client_operation_id', $clientOperationId)
            ->where('learning_group_id', $group->id)
            ->first();

        if ($operation === null) {
            throw new GroupAttendanceCardBulkException(
                GroupAttendanceCardBulkResultCode::FAILED_VALIDATION,
                'Bulk operation not found.',
                404,
            );
        }

        return $this->formatOperationPayload($operation, includeTokens: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function processBatchWithinGroupLock(
        LearningGroup $group,
        User $admin,
        string $clientOperationId,
        string $mode,
        bool $confirmReissue,
        ?string $revocationReason,
        string $fingerprint,
        int $batchIndex,
        int $fromSequence,
        int $toSequence,
    ): array {
        $operation = GroupAttendanceCardBulkOperation::query()
            ->where('client_operation_id', $clientOperationId)
            ->first();

        if ($operation !== null) {
            if ($operation->learning_group_id !== $group->id) {
                throw new GroupAttendanceCardBulkException(
                    GroupAttendanceCardBulkResultCode::OPERATION_FINGERPRINT_MISMATCH,
                    'Operation identifier is already used for another learning group.',
                    409,
                );
            }

            if ($operation->request_fingerprint !== $fingerprint) {
                throw new GroupAttendanceCardBulkException(
                    GroupAttendanceCardBulkResultCode::OPERATION_FINGERPRINT_MISMATCH,
                    'Operation request fingerprint mismatch.',
                    409,
                );
            }
        } else {
            $operation = $this->createOperationWithSnapshot(
                $group,
                $admin,
                $clientOperationId,
                $mode,
                $confirmReissue,
                $revocationReason,
                $fingerprint,
            );
        }

        if ($this->operationRequiresReissueConfirm($operation) && ! $operation->confirm_reissue) {
            throw new GroupAttendanceCardBulkException(
                GroupAttendanceCardBulkResultCode::CONFIRM_REISSUE_REQUIRED,
                'Explicit reissue confirmation is required for this operation.',
                422,
            );
        }

        if ($operation->snapshot_count === 0) {
            $this->markOperationCompletedIfDone($operation);

            return $this->formatBatchResponse(
                $operation,
                $fromSequence,
                $toSequence,
                [],
                replay: false,
                includeTokens: false,
            );
        }

        if ($toSequence >= $operation->snapshot_count) {
            throw new GroupAttendanceCardBulkException(
                GroupAttendanceCardBulkResultCode::CURSOR_OUT_OF_ORDER,
                'Batch cursor exceeds operation snapshot.',
                409,
            );
        }

        $expectedBatchIndex = (int) $operation->next_batch_index;
        $isReplay = $batchIndex < $expectedBatchIndex;
        $isCurrent = $batchIndex === $expectedBatchIndex;

        if ($batchIndex > $expectedBatchIndex) {
            throw new GroupAttendanceCardBulkException(
                GroupAttendanceCardBulkResultCode::CURSOR_OUT_OF_ORDER,
                'A previous batch must be completed before this batch.',
                409,
            );
        }

        [$expectedFrom, $expectedTo] = $this->batchBounds($batchIndex, $operation->snapshot_count);

        if ($fromSequence !== $expectedFrom || $toSequence !== $expectedTo) {
            throw new GroupAttendanceCardBulkException(
                GroupAttendanceCardBulkResultCode::CURSOR_OUT_OF_ORDER,
                'Batch cursor does not match the expected batch range.',
                409,
            );
        }

        $operation->update(['status' => GroupAttendanceCardBulkOperation::STATUS_PROCESSING]);

        $studentResults = [];
        $includeTokens = ! $isReplay;

        if (! $isReplay) {
            for ($sequence = $fromSequence; $sequence <= $toSequence; $sequence++) {
                $studentResults[] = $this->processOneStudent(
                    $operation,
                    $group,
                    $admin,
                    $sequence,
                );
            }

            if ($this->batchRangeIsTerminal($operation, $fromSequence, $toSequence)) {
                $operation->refresh();
                $operation->update(['next_batch_index' => $batchIndex + 1]);
            }

            $this->markOperationCompletedIfDone($operation->fresh());
        } else {
            $rows = GroupAttendanceCardBulkOperationStudent::query()
                ->where('group_attendance_card_bulk_operation_id', $operation->id)
                ->whereBetween('sequence', [$fromSequence, $toSequence])
                ->orderBy('sequence')
                ->get();

            foreach ($rows as $row) {
                $studentResults[] = $this->formatStudentResult($row, $group, includeToken: false, replay: true);
            }
        }

        return $this->formatBatchResponse(
            $operation->fresh(),
            $fromSequence,
            $toSequence,
            $studentResults,
            replay: $isReplay,
            includeTokens: $includeTokens && ! $isReplay,
        );
    }

    private function createOperationWithSnapshot(
        LearningGroup $group,
        User $admin,
        string $clientOperationId,
        string $mode,
        bool $confirmReissue,
        ?string $revocationReason,
        string $fingerprint,
    ): GroupAttendanceCardBulkOperation {
        return DB::transaction(function () use (
            $group,
            $admin,
            $clientOperationId,
            $mode,
            $confirmReissue,
            $revocationReason,
            $fingerprint,
        ): GroupAttendanceCardBulkOperation {
            $enrollments = $this->eligibilityService->fetchGroupEnrollments($group);
            $eligible = $enrollments->filter(
                fn (object $row): bool => $this->eligibilityService->isEnrollmentEligibleForIssuance($row)
            )->values();

            $studentIds = $eligible->pluck('student_id')->unique()->values()->all();
            $activeCards = $this->eligibilityService->loadActiveCardsByStudentId($studentIds);

            $operation = GroupAttendanceCardBulkOperation::query()->create([
                'client_operation_id' => $clientOperationId,
                'learning_group_id' => $group->id,
                'initiated_by_user_id' => $admin->id,
                'mode' => $mode,
                'confirm_reissue' => $confirmReissue,
                'revocation_reason' => $revocationReason !== null ? mb_substr($revocationReason, 0, 500) : null,
                'request_fingerprint' => $fingerprint,
                'snapshot_count' => $eligible->count(),
                'status' => GroupAttendanceCardBulkOperation::STATUS_OPEN,
                'next_batch_index' => 0,
            ]);

            foreach ($eligible as $index => $row) {
                $studentId = (int) $row->student_id;
                $hasActive = $activeCards->has($studentId);

                GroupAttendanceCardBulkOperationStudent::query()->create([
                    'group_attendance_card_bulk_operation_id' => $operation->id,
                    'student_id' => $studentId,
                    'sequence' => $index,
                    'planned_action' => $this->eligibilityService->resolvePlannedAction($mode, $hasActive),
                    'status' => GroupAttendanceCardBulkOperationStudent::STATUS_PENDING,
                ]);
            }

            return $operation;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function processOneStudent(
        GroupAttendanceCardBulkOperation $operation,
        LearningGroup $group,
        User $admin,
        int $sequence,
    ): array {
        $plainToken = null;
        $tokenAvailable = false;

        $resultRow = DB::transaction(function () use (
            $operation,
            $group,
            $admin,
            $sequence,
            &$plainToken,
            &$tokenAvailable,
        ): GroupAttendanceCardBulkOperationStudent {
            /** @var GroupAttendanceCardBulkOperationStudent|null $opStudent */
            $opStudent = GroupAttendanceCardBulkOperationStudent::query()
                ->where('group_attendance_card_bulk_operation_id', $operation->id)
                ->where('sequence', $sequence)
                ->lockForUpdate()
                ->first();

            if ($opStudent === null) {
                throw new RuntimeException('Operation student row missing for sequence '.$sequence);
            }

            if ($opStudent->status === GroupAttendanceCardBulkOperationStudent::STATUS_SUCCESS) {
                return $opStudent;
            }

            if ($opStudent->status === GroupAttendanceCardBulkOperationStudent::STATUS_SKIPPED
                || $opStudent->status === GroupAttendanceCardBulkOperationStudent::STATUS_FAILED) {
                return $opStudent;
            }

            if ($opStudent->status === GroupAttendanceCardBulkOperationStudent::STATUS_PROCESSING) {
                $recovered = $this->attemptProcessingRecovery($operation, $opStudent);
                if ($recovered !== null) {
                    return $recovered;
                }
            }

            $opStudent->update(['status' => GroupAttendanceCardBulkOperationStudent::STATUS_PROCESSING]);

            $enrollmentRow = $this->eligibilityService->findEligibleEnrollmentRow($group, $opStudent->student_id);

            if ($enrollmentRow === null) {
                return $this->finalizeSkipped(
                    $opStudent,
                    GroupAttendanceCardBulkResultCode::SKIP_NOT_ELIGIBLE,
                );
            }

            if ($opStudent->planned_action === GroupAttendanceCardBulkOperationStudent::PLANNED_SKIP) {
                return $this->finalizeSkipped(
                    $opStudent,
                    GroupAttendanceCardBulkResultCode::SKIP_NO_ACTION_FOR_MODE,
                );
            }

            $lockedStudent = Student::query()->lockForUpdate()->find($opStudent->student_id);

            if ($lockedStudent === null) {
                return $this->finalizeFailed($opStudent, GroupAttendanceCardBulkResultCode::FAILED_VALIDATION);
            }

            $hasActive = StudentAttendanceCard::query()
                ->where('student_id', $lockedStudent->id)
                ->active()
                ->exists();

            $action = $this->resolveExecutionAction($operation->mode, $hasActive);

            if ($action === null) {
                return $this->finalizeSkipped(
                    $opStudent,
                    GroupAttendanceCardBulkResultCode::SKIP_ELIGIBILITY_CHANGED,
                );
            }

            try {
                $issued = $action === GroupAttendanceCardBulkOperationStudent::PLANNED_ISSUE
                    ? $this->lifecycleService->issueOnLockedStudent($admin, $lockedStudent, $operation->id)
                    : $this->lifecycleService->reissueOnLockedStudent(
                        $admin,
                        $lockedStudent,
                        $operation->revocation_reason,
                        $operation->id,
                    );

                $resultCode = $action === GroupAttendanceCardBulkOperationStudent::PLANNED_ISSUE
                    ? GroupAttendanceCardBulkResultCode::ISSUED
                    : GroupAttendanceCardBulkResultCode::REISSUED;

                $card = $issued->card->fresh();

                if ($card->group_attendance_card_bulk_operation_id !== $operation->id) {
                    throw new RuntimeException('Bulk operation link was not persisted on the card.');
                }

                $opStudent->update([
                    'status' => GroupAttendanceCardBulkOperationStudent::STATUS_SUCCESS,
                    'result_code' => $resultCode,
                    'card_id' => $card->id,
                    'processed_at' => now(),
                ]);

                if ($opStudent->token_response_generated_at === null) {
                    $plainToken = $issued->plainTextToken;
                    $tokenAvailable = true;
                    $opStudent->update(['token_response_generated_at' => now()]);
                }

                return $opStudent->fresh();
            } catch (InvalidArgumentException $e) {
                return $this->finalizeFailed(
                    $opStudent,
                    GroupAttendanceCardBulkResultCode::FAILED_VALIDATION,
                );
            } catch (AuthorizationException) {
                throw new GroupAttendanceCardBulkException(
                    GroupAttendanceCardBulkResultCode::FAILED_VALIDATION,
                    'Unauthorized.',
                    403,
                );
            } catch (Throwable) {
                return $this->finalizeFailed(
                    $opStudent,
                    GroupAttendanceCardBulkResultCode::FAILED_PROCESSING,
                );
            }
        });

        return $this->formatStudentResult(
            $resultRow,
            $group,
            includeToken: $tokenAvailable,
            replay: $resultRow->token_response_generated_at !== null && ! $tokenAvailable,
            plainToken: $plainToken,
        );
    }

    private function attemptProcessingRecovery(
        GroupAttendanceCardBulkOperation $operation,
        GroupAttendanceCardBulkOperationStudent $opStudent,
    ): ?GroupAttendanceCardBulkOperationStudent {
        if ($opStudent->card_id !== null) {
            $card = StudentAttendanceCard::query()->find($opStudent->card_id);

            if ($card !== null
                && $card->group_attendance_card_bulk_operation_id === $operation->id
                && $opStudent->status !== GroupAttendanceCardBulkOperationStudent::STATUS_SUCCESS) {
                $opStudent->update([
                    'status' => GroupAttendanceCardBulkOperationStudent::STATUS_SUCCESS,
                    'processed_at' => $opStudent->processed_at ?? now(),
                    'result_code' => $opStudent->result_code ?? GroupAttendanceCardBulkResultCode::ISSUED,
                ]);

                return $opStudent->fresh();
            }
        }

        $linkedCard = StudentAttendanceCard::query()
            ->where('student_id', $opStudent->student_id)
            ->where('group_attendance_card_bulk_operation_id', $operation->id)
            ->orderByDesc('id')
            ->first();

        if ($linkedCard !== null && $opStudent->card_id === null) {
            $opStudent->update([
                'status' => GroupAttendanceCardBulkOperationStudent::STATUS_SUCCESS,
                'card_id' => $linkedCard->id,
                'processed_at' => $opStudent->processed_at ?? now(),
                'result_code' => $opStudent->result_code ?? GroupAttendanceCardBulkResultCode::ISSUED,
            ]);

            return $opStudent->fresh();
        }

        if ($linkedCard === null && $opStudent->card_id === null) {
            return null;
        }

        $this->finalizeFailed($opStudent, GroupAttendanceCardBulkResultCode::RECOVERY_AMBIGUOUS);

        return $opStudent->fresh();
    }

    private function finalizeSkipped(
        GroupAttendanceCardBulkOperationStudent $opStudent,
        string $code,
    ): GroupAttendanceCardBulkOperationStudent {
        $opStudent->update([
            'status' => GroupAttendanceCardBulkOperationStudent::STATUS_SKIPPED,
            'result_code' => $code,
            'processed_at' => now(),
        ]);

        return $opStudent->fresh();
    }

    private function finalizeFailed(
        GroupAttendanceCardBulkOperationStudent $opStudent,
        string $code,
    ): GroupAttendanceCardBulkOperationStudent {
        $opStudent->update([
            'status' => GroupAttendanceCardBulkOperationStudent::STATUS_FAILED,
            'result_code' => $code,
            'processed_at' => now(),
        ]);

        return $opStudent->fresh();
    }

    private function resolveExecutionAction(string $mode, bool $hasActiveCard): ?string
    {
        return match ($mode) {
            GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY => $hasActiveCard
                ? null
                : GroupAttendanceCardBulkOperationStudent::PLANNED_ISSUE,
            GroupAttendanceCardBulkOperation::MODE_REISSUE_ACTIVE_ONLY => $hasActiveCard
                ? GroupAttendanceCardBulkOperationStudent::PLANNED_REISSUE
                : null,
            GroupAttendanceCardBulkOperation::MODE_ISSUE_ALL => $hasActiveCard
                ? GroupAttendanceCardBulkOperationStudent::PLANNED_REISSUE
                : GroupAttendanceCardBulkOperationStudent::PLANNED_ISSUE,
            default => null,
        };
    }

    private function operationRequiresReissueConfirm(GroupAttendanceCardBulkOperation $operation): bool
    {
        if ($operation->mode === GroupAttendanceCardBulkOperation::MODE_REISSUE_ACTIVE_ONLY) {
            return true;
        }

        if ($operation->mode !== GroupAttendanceCardBulkOperation::MODE_ISSUE_ALL) {
            return false;
        }

        return GroupAttendanceCardBulkOperationStudent::query()
            ->where('group_attendance_card_bulk_operation_id', $operation->id)
            ->where('planned_action', GroupAttendanceCardBulkOperationStudent::PLANNED_REISSUE)
            ->exists();
    }

    private function batchRangeIsTerminal(
        GroupAttendanceCardBulkOperation $operation,
        int $fromSequence,
        int $toSequence,
    ): bool {
        $pending = GroupAttendanceCardBulkOperationStudent::query()
            ->where('group_attendance_card_bulk_operation_id', $operation->id)
            ->whereBetween('sequence', [$fromSequence, $toSequence])
            ->whereIn('status', [
                GroupAttendanceCardBulkOperationStudent::STATUS_PENDING,
                GroupAttendanceCardBulkOperationStudent::STATUS_PROCESSING,
            ])
            ->exists();

        return ! $pending;
    }

    private function markOperationCompletedIfDone(?GroupAttendanceCardBulkOperation $operation): void
    {
        if ($operation === null) {
            return;
        }

        $open = GroupAttendanceCardBulkOperationStudent::query()
            ->where('group_attendance_card_bulk_operation_id', $operation->id)
            ->whereIn('status', [
                GroupAttendanceCardBulkOperationStudent::STATUS_PENDING,
                GroupAttendanceCardBulkOperationStudent::STATUS_PROCESSING,
            ])
            ->exists();

        $allBatchesDone = $operation->next_batch_index >= $this->totalBatchCount($operation->snapshot_count);

        if (! $open && ($operation->snapshot_count === 0 || $allBatchesDone)) {
            $operation->update([
                'status' => GroupAttendanceCardBulkOperation::STATUS_COMPLETED,
                'completed_at' => $operation->completed_at ?? now(),
            ]);
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function batchBounds(int $batchIndex, int $snapshotCount): array
    {
        $from = $batchIndex * GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX;
        $to = min(
            $from + GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX - 1,
            max(0, $snapshotCount - 1),
        );

        return [$from, $to];
    }

    private function totalBatchCount(int $snapshotCount): int
    {
        if ($snapshotCount === 0) {
            return 0;
        }

        return (int) ceil($snapshotCount / GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX);
    }

    private function buildFingerprint(
        LearningGroup $group,
        User $admin,
        string $mode,
        bool $confirmReissue,
        ?string $revocationReason,
    ): string {
        $payload = [
            'learning_group_id' => $group->id,
            'initiated_by_user_id' => $admin->id,
            'mode' => $mode,
            'confirm_reissue' => $confirmReissue,
            'revocation_reason' => $revocationReason !== null ? mb_substr($revocationReason, 0, 500) : null,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function groupLockKey(int $groupId): string
    {
        return 'attendance-cards:bulk-group:'.$groupId;
    }

    private function assertAdmin(User $admin): void
    {
        if (! $admin->hasRole('admin')) {
            throw new AuthorizationException('Only administrators may manage student attendance cards.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBatchResponse(
        GroupAttendanceCardBulkOperation $operation,
        int $fromSequence,
        int $toSequence,
        array $studentResults,
        bool $replay,
        bool $includeTokens,
    ): array {
        $batchIndex = (int) ($fromSequence / GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX);
        $totalBatches = $this->totalBatchCount($operation->snapshot_count);
        $hasMore = $operation->next_batch_index < $totalBatches;

        return [
            'operation' => $this->formatOperationMeta($operation),
            'batch' => [
                'batch_index' => $batchIndex,
                'from_sequence' => $fromSequence,
                'to_sequence' => $toSequence,
                'replay' => $replay,
                'has_more' => $hasMore,
                'next_batch' => $hasMore
                    ? [
                        'from_sequence' => $operation->next_batch_index * GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX,
                        'to_sequence' => $this->batchBounds($operation->next_batch_index, $operation->snapshot_count)[1],
                    ]
                    : null,
            ],
            'students' => $studentResults,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOperationPayload(
        GroupAttendanceCardBulkOperation $operation,
        bool $includeTokens,
    ): array {
        $students = GroupAttendanceCardBulkOperationStudent::query()
            ->where('group_attendance_card_bulk_operation_id', $operation->id)
            ->orderBy('sequence')
            ->get()
            ->map(fn (GroupAttendanceCardBulkOperationStudent $row): array => $this->formatStudentResult(
                $row,
                $operation->learningGroup,
                includeToken: false,
                replay: true,
            ))
            ->all();

        return [
            'operation' => $this->formatOperationMeta($operation),
            'students' => $students,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOperationMeta(GroupAttendanceCardBulkOperation $operation): array
    {
        return [
            'client_operation_id' => $operation->client_operation_id,
            'learning_group_id' => $operation->learning_group_id,
            'mode' => $operation->mode,
            'status' => $operation->status,
            'snapshot_count' => $operation->snapshot_count,
            'next_batch_index' => $operation->next_batch_index,
            'completed_at' => $operation->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStudentResult(
        GroupAttendanceCardBulkOperationStudent $row,
        LearningGroup $group,
        bool $includeToken,
        bool $replay,
        ?string $plainToken = null,
    ): array {
        $student = $row->student ?? Student::query()->find($row->student_id);
        $warnings = $student !== null ? $this->eligibilityService->inactiveStudentWarnings($student) : [];

        $payload = [
            'sequence' => $row->sequence,
            'student_id' => $row->student_id,
            'planned_action' => $row->planned_action,
            'status' => $row->status,
            'result_code' => $row->result_code,
            'replay' => $replay,
            'token_available' => $includeToken && $plainToken !== null,
            'warnings' => $warnings,
        ];

        if ($row->card_id !== null) {
            $card = $row->card ?? StudentAttendanceCard::query()->find($row->card_id);
            if ($card !== null) {
                $payload['card'] = new AdminStudentAttendanceCardResource($card);
            }
        }

        if ($includeToken && $plainToken !== null && $row->status === GroupAttendanceCardBulkOperationStudent::STATUS_SUCCESS) {
            $payload['plain_text_token'] = $plainToken;
        }

        return $payload;
    }
}
