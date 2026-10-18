<?php

/**
 * MySQL row-lock and bulk/idempotency concurrency checks.
 *
 * Run only against a dedicated non-production database:
 *   php artisan migrate --database=mysql --env=testing  (after creating t_square_lms_test)
 *   vendor/bin/pest -c phpunit.mysql-concurrency.xml
 *
 * Refuses to run when DB_DATABASE is not a *test* database name.
 */

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GroupAttendanceCardBulkOperation;
use App\Models\GroupAttendanceCardBulkOperationStudent;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Models\User;
use App\Services\Attendance\DTO\IssuedStudentAttendanceCard;
use App\Services\Attendance\GroupAttendanceCardBulkResultCode;
use App\Services\Attendance\GroupAttendanceCardsPreviewService;
use App\Services\Attendance\StudentAttendanceCardLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (config('database.default') !== 'mysql') {
        $this->markTestSkipped('MySQL concurrency suite requires DB_CONNECTION=mysql (see phpunit.mysql-concurrency.xml).');
    }

    $database = (string) config('database.connections.mysql.database');
    if (! preg_match('/test/i', $database)) {
        $this->markTestSkipped('Refusing MySQL concurrency tests on non-test database: '.$database);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);

    $this->instructor = Instructor::factory()->create();
    $this->course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    $this->group = LearningGroup::create([
        'group_name' => 'Concurrency Group',
        'course_id' => $this->course->id,
        'course_instructor_id' => courseInstructorIdFor($this->course, $this->instructor),
        'enrolled_students' => 0,
    ]);
});

function concurrencyEnroll(Course $course, LearningGroup $group): Student
{
    $student = Student::factory()->create();
    $order = Order::create([
        'student_id' => $student->id,
        'total_amount' => 500,
        'status' => 'completed',
        'billing_name' => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);
    Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $group->id,
        'attendance_type' => 'offline',
        'price_paid' => 500,
    ]);

    return $student;
}

function concurrencyRequestFingerprint(
    LearningGroup $group,
    User $admin,
    string $mode = GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY,
    bool $confirmReissue = false,
    ?string $revocationReason = null,
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

function concurrencyBatchUrl(LearningGroup $group): string
{
    return '/api/admin/learning-groups/'.$group->id.'/attendance-cards/batch';
}

function concurrencyStatusUrl(LearningGroup $group, string $clientOperationId): string
{
    return '/api/admin/learning-groups/'.$group->id.'/attendance-cards/batch/'.$clientOperationId;
}

function concurrencyBatchPayload(string $clientOperationId): array
{
    return [
        'client_operation_id' => $clientOperationId,
        'mode' => GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY,
        'confirm_reissue' => false,
        'batch_cursor' => ['from_sequence' => 0, 'to_sequence' => 0],
    ];
}

function concurrencySeedStuckBulkOperation(
    LearningGroup $group,
    User $admin,
    Student $student,
    string $clientOperationId,
): GroupAttendanceCardBulkOperation {
    $mode = GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY;

    $operation = GroupAttendanceCardBulkOperation::query()->create([
        'client_operation_id' => $clientOperationId,
        'learning_group_id' => $group->id,
        'initiated_by_user_id' => $admin->id,
        'mode' => $mode,
        'confirm_reissue' => false,
        'request_fingerprint' => concurrencyRequestFingerprint($group, $admin, $mode, false, null),
        'snapshot_count' => 1,
        'status' => GroupAttendanceCardBulkOperation::STATUS_PROCESSING,
        'next_batch_index' => 0,
    ]);

    GroupAttendanceCardBulkOperationStudent::query()->create([
        'group_attendance_card_bulk_operation_id' => $operation->id,
        'student_id' => $student->id,
        'sequence' => 0,
        'planned_action' => GroupAttendanceCardBulkOperationStudent::PLANNED_ISSUE,
        'status' => GroupAttendanceCardBulkOperationStudent::STATUS_PROCESSING,
    ]);

    return $operation;
}

function concurrencyCreateBulkLinkedCard(
    Student $student,
    User $admin,
    GroupAttendanceCardBulkOperation $operation,
): StudentAttendanceCard {
    $plainToken = 'card_'.bin2hex(random_bytes(32));

    return StudentAttendanceCard::query()->create([
        'student_id' => $student->id,
        'token_hash' => hash('sha256', $plainToken),
        'token_suffix' => mb_substr($plainToken, -4),
        'issued_at' => now(),
        'issued_by_user_id' => $admin->id,
        'group_attendance_card_bulk_operation_id' => $operation->id,
    ]);
}

function concurrencyAssertStatusPayloadHasNoSecrets(\Illuminate\Testing\TestResponse $status): void
{
    $json = json_encode($status->json());
    expect($json)->not->toContain('plain_text_token')
        ->and($json)->not->toContain('token_hash');
}

it('blocks individual duplicate issue after bulk issuance on mysql', function (): void {
    $student = concurrencyEnroll($this->course, $this->group);

    $this->postJson(
        '/api/admin/learning-groups/'.$this->group->id.'/attendance-cards/batch',
        [
            'client_operation_id' => (string) Str::uuid(),
            'mode' => GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY,
            'confirm_reissue' => false,
            'batch_cursor' => ['from_sequence' => 0, 'to_sequence' => 0],
        ],
    )->assertSuccessful();

    $this->postJson('/api/admin/students/'.$student->id.'/attendance-card')
        ->assertStatus(422)
        ->assertJsonPath('code', 'CARD_ALREADY_ACTIVE');

    expect(StudentAttendanceCard::query()->active()->where('student_id', $student->id)->count())->toBe(1);
});

it('returns group lock conflict for overlapping bulk operations on mysql', function (): void {
    $student = concurrencyEnroll($this->course, $this->group);
    expect($student)->not->toBeNull();

    $lockKey = 'attendance-cards:bulk-group:'.$this->group->id;
    $hold = Cache::lock($lockKey, 30);
    expect($hold->get())->toBeTrue();

    try {
        $response = $this->postJson(
            '/api/admin/learning-groups/'.$this->group->id.'/attendance-cards/batch',
            [
                'client_operation_id' => (string) Str::uuid(),
                'mode' => GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY,
                'confirm_reissue' => false,
                'batch_cursor' => ['from_sequence' => 0, 'to_sequence' => 0],
            ],
        );

        $response->assertStatus(409);
        expect(StudentAttendanceCard::query()->where('student_id', $student->id)->count())->toBe(0);
    } finally {
        $hold->release();
    }
});

it('does not duplicate cards when the same batch cursor is replayed under mysql', function (): void {
    concurrencyEnroll($this->course, $this->group);
    $operationId = (string) Str::uuid();
    $url = '/api/admin/learning-groups/'.$this->group->id.'/attendance-cards/batch';
    $payload = [
        'client_operation_id' => $operationId,
        'mode' => GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY,
        'confirm_reissue' => false,
        'batch_cursor' => ['from_sequence' => 0, 'to_sequence' => 0],
    ];

    $this->postJson($url, $payload)->assertSuccessful();
    $this->postJson($url, $payload)->assertSuccessful();

    expect(StudentAttendanceCard::query()->active()->count())->toBe(1);
});

it('rejects out-of-order batch cursor under mysql', function (): void {
    for ($i = 0; $i < 51; $i++) {
        concurrencyEnroll($this->course, $this->group);
    }

    $operationId = (string) Str::uuid();
    $max = GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX;

    $this->postJson(
        '/api/admin/learning-groups/'.$this->group->id.'/attendance-cards/batch',
        [
            'client_operation_id' => $operationId,
            'mode' => GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY,
            'confirm_reissue' => false,
            'batch_cursor' => ['from_sequence' => $max, 'to_sequence' => $max],
        ],
    )->assertStatus(409);
});

it('recovers processing row via card_id linked to the same bulk operation on mysql', function (): void {
    $student = concurrencyEnroll($this->course, $this->group);
    $clientOperationId = (string) Str::uuid();
    $operation = concurrencySeedStuckBulkOperation($this->group, $this->admin, $student, $clientOperationId);
    $card = concurrencyCreateBulkLinkedCard($student, $this->admin, $operation);

    $opStudent = GroupAttendanceCardBulkOperationStudent::query()
        ->where('group_attendance_card_bulk_operation_id', $operation->id)
        ->firstOrFail();
    $opStudent->update(['card_id' => $card->id]);

    $response = $this->postJson(
        concurrencyBatchUrl($this->group),
        concurrencyBatchPayload($clientOperationId),
    )->assertSuccessful();

    expect($response->json('data.students.0.status'))->toBe('success')
        ->and($response->json('data.students.0.result_code'))->toBe(GroupAttendanceCardBulkResultCode::ISSUED)
        ->and($response->json('data.students.0.plain_text_token'))->toBeNull()
        ->and($response->json('data.students.0.token_available'))->toBeFalse()
        ->and($response->json('data.students.0.replay'))->toBeFalse();

    $opStudent->refresh();
    expect($opStudent->status)->toBe(GroupAttendanceCardBulkOperationStudent::STATUS_SUCCESS)
        ->and($opStudent->card_id)->toBe($card->id)
        ->and(StudentAttendanceCard::query()->active()->where('student_id', $student->id)->count())->toBe(1);

    concurrencyAssertStatusPayloadHasNoSecrets(
        $this->getJson(concurrencyStatusUrl($this->group, $clientOperationId))->assertSuccessful()
    );
});

it('recovers processing row when card_id is null but a bulk-linked card exists on mysql', function (): void {
    $student = concurrencyEnroll($this->course, $this->group);
    $clientOperationId = (string) Str::uuid();
    $operation = concurrencySeedStuckBulkOperation($this->group, $this->admin, $student, $clientOperationId);
    $card = concurrencyCreateBulkLinkedCard($student, $this->admin, $operation);

    $opStudent = GroupAttendanceCardBulkOperationStudent::query()
        ->where('group_attendance_card_bulk_operation_id', $operation->id)
        ->firstOrFail();
    expect($opStudent->card_id)->toBeNull();

    $response = $this->postJson(
        concurrencyBatchUrl($this->group),
        concurrencyBatchPayload($clientOperationId),
    )->assertSuccessful();

    expect($response->json('data.students.0.status'))->toBe('success')
        ->and($response->json('data.students.0.result_code'))->toBe(GroupAttendanceCardBulkResultCode::ISSUED)
        ->and($response->json('data.students.0.plain_text_token'))->toBeNull()
        ->and($response->json('data.students.0.token_available'))->toBeFalse();

    $opStudent->refresh();
    expect($opStudent->status)->toBe(GroupAttendanceCardBulkOperationStudent::STATUS_SUCCESS)
        ->and($opStudent->card_id)->toBe($card->id);

    concurrencyAssertStatusPayloadHasNoSecrets(
        $this->getJson(concurrencyStatusUrl($this->group, $clientOperationId))->assertSuccessful()
    );
});

it('marks recovered batch rows as replay without returning a plain token when token was already sent on mysql', function (): void {
    $student = concurrencyEnroll($this->course, $this->group);
    $clientOperationId = (string) Str::uuid();
    $operation = concurrencySeedStuckBulkOperation($this->group, $this->admin, $student, $clientOperationId);
    $card = concurrencyCreateBulkLinkedCard($student, $this->admin, $operation);

    $opStudent = GroupAttendanceCardBulkOperationStudent::query()
        ->where('group_attendance_card_bulk_operation_id', $operation->id)
        ->firstOrFail();
    $opStudent->update([
        'card_id' => $card->id,
        'token_response_generated_at' => now(),
    ]);

    $response = $this->postJson(
        concurrencyBatchUrl($this->group),
        concurrencyBatchPayload($clientOperationId),
    )->assertSuccessful();

    expect($response->json('data.students.0.status'))->toBe('success')
        ->and($response->json('data.students.0.replay'))->toBeTrue()
        ->and($response->json('data.students.0.plain_text_token'))->toBeNull()
        ->and($response->json('data.students.0.token_available'))->toBeFalse();

    concurrencyAssertStatusPayloadHasNoSecrets(
        $this->getJson(concurrencyStatusUrl($this->group, $clientOperationId))->assertSuccessful()
    );
});

it('fails ambiguous processing recovery without reissuing when card_id does not match the operation on mysql', function (): void {
    $student = concurrencyEnroll($this->course, $this->group);
    $clientOperationId = (string) Str::uuid();
    $operation = concurrencySeedStuckBulkOperation($this->group, $this->admin, $student, $clientOperationId);

    $plainToken = 'card_'.bin2hex(random_bytes(32));
    $mislinkedCard = StudentAttendanceCard::query()->create([
        'student_id' => $student->id,
        'token_hash' => hash('sha256', $plainToken),
        'token_suffix' => mb_substr($plainToken, -4),
        'issued_at' => now(),
        'issued_by_user_id' => $this->admin->id,
        'group_attendance_card_bulk_operation_id' => null,
    ]);

    $opStudent = GroupAttendanceCardBulkOperationStudent::query()
        ->where('group_attendance_card_bulk_operation_id', $operation->id)
        ->firstOrFail();
    $opStudent->update(['card_id' => $mislinkedCard->id]);

    $response = $this->postJson(
        concurrencyBatchUrl($this->group),
        concurrencyBatchPayload($clientOperationId),
    )->assertSuccessful();

    expect($response->json('data.students.0.status'))->toBe('failed')
        ->and($response->json('data.students.0.result_code'))->toBe(GroupAttendanceCardBulkResultCode::RECOVERY_AMBIGUOUS)
        ->and($response->json('data.students.0.plain_text_token'))->toBeNull()
        ->and($response->json('data.students.0.token_available'))->toBeFalse();

    $opStudent->refresh();
    expect($opStudent->status)->toBe(GroupAttendanceCardBulkOperationStudent::STATUS_FAILED)
        ->and(StudentAttendanceCard::query()->active()->where('student_id', $student->id)->count())->toBe(1)
        ->and(StudentAttendanceCard::query()->active()->where('student_id', $student->id)->value('id'))->toBe($mislinkedCard->id);

    concurrencyAssertStatusPayloadHasNoSecrets(
        $this->getJson(concurrencyStatusUrl($this->group, $clientOperationId))->assertSuccessful()
    );
});

it('fails ambiguous recovery when card_id points at another operations card while a conflicting link exists on mysql', function (): void {
    $student = concurrencyEnroll($this->course, $this->group);
    $clientOperationId = (string) Str::uuid();
    $operation = concurrencySeedStuckBulkOperation($this->group, $this->admin, $student, $clientOperationId);

    $otherOperation = GroupAttendanceCardBulkOperation::query()->create([
        'client_operation_id' => (string) Str::uuid(),
        'learning_group_id' => $this->group->id,
        'initiated_by_user_id' => $this->admin->id,
        'mode' => GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY,
        'confirm_reissue' => false,
        'request_fingerprint' => concurrencyRequestFingerprint($this->group, $this->admin),
        'snapshot_count' => 0,
        'status' => GroupAttendanceCardBulkOperation::STATUS_OPEN,
        'next_batch_index' => 0,
    ]);

    $cardForOtherOperation = concurrencyCreateBulkLinkedCard($student, $this->admin, $otherOperation);
    $mislinkedCard = concurrencyCreateBulkLinkedCard($student, $this->admin, $operation);

    $opStudent = GroupAttendanceCardBulkOperationStudent::query()
        ->where('group_attendance_card_bulk_operation_id', $operation->id)
        ->firstOrFail();
    $opStudent->update(['card_id' => $cardForOtherOperation->id]);

    expect(
        StudentAttendanceCard::query()
            ->where('student_id', $student->id)
            ->where('group_attendance_card_bulk_operation_id', $operation->id)
            ->exists()
    )->toBeTrue();

    $response = $this->postJson(
        concurrencyBatchUrl($this->group),
        concurrencyBatchPayload($clientOperationId),
    )->assertSuccessful();

    expect($response->json('data.students.0.status'))->toBe('failed')
        ->and($response->json('data.students.0.result_code'))->toBe(GroupAttendanceCardBulkResultCode::RECOVERY_AMBIGUOUS);

    $opStudent->refresh();
    expect($opStudent->status)->toBe(GroupAttendanceCardBulkOperationStudent::STATUS_FAILED)
        ->and(StudentAttendanceCard::query()->active()->where('student_id', $student->id)->count())->toBe(2);

    concurrencyAssertStatusPayloadHasNoSecrets(
        $this->getJson(concurrencyStatusUrl($this->group, $clientOperationId))->assertSuccessful()
    );

    expect($mislinkedCard->fresh()->isActive())->toBeTrue();
});

function concurrencySimulateBrokenBulkLinkOnIssue(
    StudentAttendanceCardLifecycleService $realLifecycle,
    int $failForCallNumber = 1,
): StudentAttendanceCardLifecycleService {
    $lifecycle = Mockery::mock($realLifecycle)->makePartial();
    $attempt = 0;
    $lifecycle->shouldReceive('issueOnLockedStudent')
        ->andReturnUsing(function (
            ?User $admin,
            Student $lockedStudent,
            ?int $bulkOperationId = null,
        ) use ($realLifecycle, $failForCallNumber, &$attempt): IssuedStudentAttendanceCard {
            $attempt++;
            $issued = $realLifecycle->issueOnLockedStudent($admin, $lockedStudent, $bulkOperationId ?? null);

            if ($attempt === $failForCallNumber) {
                $issued->card->forceFill(['group_attendance_card_bulk_operation_id' => null])->save();

                return new IssuedStudentAttendanceCard($issued->card->fresh(), $issued->plainTextToken);
            }

            return $issued;
        });

    return $lifecycle;
}

it('rolls back orphan card when bulk link verification fails after card persistence on mysql', function (): void {
    $student = concurrencyEnroll($this->course, $this->group);
    $clientOperationId = (string) Str::uuid();

    $realLifecycle = app(StudentAttendanceCardLifecycleService::class);
    $this->app->instance(
        StudentAttendanceCardLifecycleService::class,
        concurrencySimulateBrokenBulkLinkOnIssue($realLifecycle),
    );

    $response = $this->postJson(
        concurrencyBatchUrl($this->group),
        concurrencyBatchPayload($clientOperationId),
    )->assertSuccessful();

    expect($response->json('data.students.0.status'))->toBe(GroupAttendanceCardBulkOperationStudent::STATUS_PENDING)
        ->and($response->json('data.students.0.result_code'))->toBeNull()
        ->and($response->json('data.students.0.plain_text_token'))->toBeNull()
        ->and($response->json('data.students.0.token_available'))->toBeFalse()
        ->and($response->json('data.students.0.card'))->toBeNull()
        ->and($response->json('data.batch.has_more'))->toBeTrue()
        ->and($response->json('data.operation.next_batch_index'))->toBe(0);

    $operation = GroupAttendanceCardBulkOperation::query()
        ->where('client_operation_id', $clientOperationId)
        ->firstOrFail();

    $opStudent = GroupAttendanceCardBulkOperationStudent::query()
        ->where('group_attendance_card_bulk_operation_id', $operation->id)
        ->where('student_id', $student->id)
        ->firstOrFail();

    expect($opStudent->status)->toBe(GroupAttendanceCardBulkOperationStudent::STATUS_PENDING)
        ->and($opStudent->result_code)->toBeNull()
        ->and($opStudent->card_id)->toBeNull();

    expect($operation->fresh()->status)->not->toBe(GroupAttendanceCardBulkOperation::STATUS_COMPLETED)
        ->and($operation->fresh()->next_batch_index)->toBe(0);

    expect(StudentAttendanceCard::query()->active()->where('student_id', $student->id)->count())->toBe(0);

    concurrencyAssertStatusPayloadHasNoSecrets(
        $this->getJson(concurrencyStatusUrl($this->group, $clientOperationId))->assertSuccessful()
    );
});

it('retries the same bulk cursor successfully after link verification rollback on mysql', function (): void {
    $student = concurrencyEnroll($this->course, $this->group);
    $clientOperationId = (string) Str::uuid();

    $realLifecycle = app(StudentAttendanceCardLifecycleService::class);
    $this->app->instance(
        StudentAttendanceCardLifecycleService::class,
        concurrencySimulateBrokenBulkLinkOnIssue($realLifecycle, failForCallNumber: 1),
    );

    $this->postJson(
        concurrencyBatchUrl($this->group),
        concurrencyBatchPayload($clientOperationId),
    )->assertSuccessful();

    expect(StudentAttendanceCard::query()->active()->where('student_id', $student->id)->count())->toBe(0);

    $retry = $this->postJson(
        concurrencyBatchUrl($this->group),
        concurrencyBatchPayload($clientOperationId),
    )->assertSuccessful();

    expect($retry->json('data.students.0.status'))->toBe('success')
        ->and($retry->json('data.students.0.result_code'))->toBe(GroupAttendanceCardBulkResultCode::ISSUED)
        ->and($retry->json('data.students.0.plain_text_token'))->toMatch('/^card_[0-9a-f]{64}$/');

    $operation = GroupAttendanceCardBulkOperation::query()
        ->where('client_operation_id', $clientOperationId)
        ->firstOrFail();

    $card = StudentAttendanceCard::query()->active()->where('student_id', $student->id)->sole();
    expect($card->group_attendance_card_bulk_operation_id)->toBe($operation->id);

    $replay = $this->postJson(
        concurrencyBatchUrl($this->group),
        concurrencyBatchPayload($clientOperationId),
    )->assertSuccessful();

    expect($replay->json('data.students.0.plain_text_token'))->toBeNull()
        ->and($replay->json('data.students.0.token_available'))->toBeFalse();

    concurrencyAssertStatusPayloadHasNoSecrets(
        $this->getJson(concurrencyStatusUrl($this->group, $clientOperationId))->assertSuccessful()
    );
});

it('rolls back reissue revocations when bulk link verification fails on mysql', function (): void {
    $student = concurrencyEnroll($this->course, $this->group);
    $existingCard = app(StudentAttendanceCardLifecycleService::class)->issue($this->admin, $student)->card;
    $clientOperationId = (string) Str::uuid();

    $realLifecycle = app(StudentAttendanceCardLifecycleService::class);
    $lifecycle = Mockery::mock($realLifecycle)->makePartial();
    $lifecycle->shouldReceive('reissueOnLockedStudent')
        ->once()
        ->andReturnUsing(function (
            ?User $admin,
            Student $lockedStudent,
            ?string $revokeReason = null,
            ?int $bulkOperationId = null,
        ) use ($realLifecycle): IssuedStudentAttendanceCard {
            $issued = $realLifecycle->reissueOnLockedStudent($admin, $lockedStudent, $revokeReason, $bulkOperationId);
            $issued->card->forceFill(['group_attendance_card_bulk_operation_id' => null])->save();

            return new IssuedStudentAttendanceCard($issued->card->fresh(), $issued->plainTextToken);
        });
    $this->app->instance(StudentAttendanceCardLifecycleService::class, $lifecycle);

    $response = $this->postJson(
        concurrencyBatchUrl($this->group),
        [
            'client_operation_id' => $clientOperationId,
            'mode' => GroupAttendanceCardBulkOperation::MODE_REISSUE_ACTIVE_ONLY,
            'confirm_reissue' => true,
            'batch_cursor' => ['from_sequence' => 0, 'to_sequence' => 0],
        ],
    )->assertSuccessful();

    expect($response->json('data.students.0.status'))->toBe(GroupAttendanceCardBulkOperationStudent::STATUS_PENDING);

    expect($existingCard->fresh()->isActive())->toBeTrue()
        ->and(StudentAttendanceCard::query()->active()->where('student_id', $student->id)->count())->toBe(1)
        ->and(StudentAttendanceCard::query()->active()->where('student_id', $student->id)->value('id'))->toBe($existingCard->id);
});
