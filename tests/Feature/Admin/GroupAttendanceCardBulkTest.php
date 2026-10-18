<?php

/**
 * Feature tests for group attendance card bulk issuance.
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
use App\Services\Attendance\GroupAttendanceCardBulkResultCode;
use App\Services\Attendance\GroupAttendanceCardsPreviewService;
use App\Services\Attendance\StudentAttendanceCardLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);

    $this->instructor = Instructor::factory()->create();
    $this->course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    $this->group = LearningGroup::create([
        'group_name' => 'Bulk Group',
        'course_id' => $this->course->id,
        'course_instructor_id' => courseInstructorIdFor($this->course, $this->instructor),
        'enrolled_students' => 0,
    ]);
});

function bulkEnroll(
    Course $course,
    LearningGroup $group,
    ?string $attendanceType = 'offline',
    string $studentStatus = 'active',
): Student {
    $student = Student::factory()->create();
    if ($studentStatus !== 'active') {
        $student->forceFill(['status' => $studentStatus])->save();
    }

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
        'attendance_type' => $attendanceType,
        'price_paid' => 500,
    ]);

    return $student;
}

function bulkBatchUrl(LearningGroup $group): string
{
    return '/api/admin/learning-groups/'.$group->id.'/attendance-cards/batch';
}

function bulkStatusUrl(LearningGroup $group, string $clientOperationId): string
{
    return '/api/admin/learning-groups/'.$group->id.'/attendance-cards/batch/'.$clientOperationId;
}

function bulkPayload(string $mode, int $from, int $to, ?string $operationId = null, bool $confirmReissue = false): array
{
    return [
        'client_operation_id' => $operationId ?? (string) Str::uuid(),
        'mode' => $mode,
        'confirm_reissue' => $confirmReissue,
        'batch_cursor' => [
            'from_sequence' => $from,
            'to_sequence' => $to,
        ],
    ];
}

it('denies guest access to bulk batch and status endpoints', function (): void {
    auth()->guard('sanctum')->forgetUser();

    $this->postJson(bulkBatchUrl($this->group), bulkPayload('issue_new_only', 0, 0))
        ->assertUnauthorized();

    $this->getJson(bulkStatusUrl($this->group, (string) Str::uuid()))
        ->assertUnauthorized();
});

it('issues cards for eligible students without active cards in issue_new_only mode', function (): void {
    $student = bulkEnroll($this->course, $this->group);

    $response = $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY, 0, 0),
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.students.0.status', 'success')
        ->assertJsonPath('data.students.0.token_available', true);

    $token = $response->json('data.students.0.plain_text_token');
    expect($token)->toMatch('/^card_[0-9a-f]{64}$/');

    expect(StudentAttendanceCard::query()->where('student_id', $student->id)->active()->count())->toBe(1);

    $opStudent = GroupAttendanceCardBulkOperationStudent::query()->first();
    expect($opStudent->card_id)->not->toBeNull();

    $card = StudentAttendanceCard::find($opStudent->card_id);
    expect($card->group_attendance_card_bulk_operation_id)->not->toBeNull();
});

it('does not return plain token on batch replay', function (): void {
    bulkEnroll($this->course, $this->group);
    $operationId = (string) Str::uuid();

    $first = $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY, 0, 0, $operationId),
    )->assertSuccessful();

    expect($first->json('data.students.0.plain_text_token'))->not->toBeNull();

    $replay = $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY, 0, 0, $operationId),
    )->assertSuccessful();

    expect($replay->json('data.students.0.plain_text_token'))->toBeNull()
        ->and($replay->json('data.students.0.token_available'))->toBeFalse()
        ->and(StudentAttendanceCard::query()->count())->toBe(1);
});

it('never exposes tokens on status endpoint', function (): void {
    bulkEnroll($this->course, $this->group);
    $operationId = (string) Str::uuid();

    $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY, 0, 0, $operationId),
    )->assertSuccessful();

    $status = $this->getJson(bulkStatusUrl($this->group, $operationId))->assertSuccessful();
    $json = json_encode($status->json());
    expect($json)->not->toContain('plain_text_token')
        ->and($json)->not->toContain('token_hash');
});

it('requires confirm_reissue for reissue_active_only mode', function (): void {
    $student = bulkEnroll($this->course, $this->group);
    app(StudentAttendanceCardLifecycleService::class)->issue($this->admin, $student);

    $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_REISSUE_ACTIVE_ONLY, 0, 0, null, false),
    )
        ->assertStatus(422)
        ->assertJsonPath('code', GroupAttendanceCardBulkResultCode::CONFIRM_REISSUE_REQUIRED);
});

it('reissues active cards when confirmed', function (): void {
    $student = bulkEnroll($this->course, $this->group);
    $old = app(StudentAttendanceCardLifecycleService::class)->issue($this->admin, $student)->card;

    $response = $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_REISSUE_ACTIVE_ONLY, 0, 0, null, true),
    )->assertSuccessful();

    expect($response->json('data.students.0.result_code'))->toBe('REISSUED');

    $old->refresh();
    expect($old->revoked_at)->not->toBeNull();

    $active = StudentAttendanceCard::query()->where('student_id', $student->id)->active()->first();
    expect($active->id)->not->toBe($old->id);
});

it('rejects operation fingerprint mismatch for same client operation id', function (): void {
    bulkEnroll($this->course, $this->group);
    $operationId = (string) Str::uuid();

    $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY, 0, 0, $operationId),
    )->assertSuccessful();

    $this->postJson(
        bulkBatchUrl($this->group),
        array_merge(
            bulkPayload(GroupAttendanceCardBulkOperation::MODE_REISSUE_ACTIVE_ONLY, 0, 0, $operationId, true),
        ),
    )
        ->assertStatus(409)
        ->assertJsonPath('code', GroupAttendanceCardBulkResultCode::OPERATION_FINGERPRINT_MISMATCH);
});

it('rejects out-of-order batch cursor', function (): void {
    for ($i = 0; $i < 51; $i++) {
        bulkEnroll($this->course, $this->group);
    }

    $operationId = (string) Str::uuid();
    $max = GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX;

    $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(
            GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY,
            $max,
            $max,
            $operationId,
        ),
    )
        ->assertStatus(409)
        ->assertJsonPath('code', GroupAttendanceCardBulkResultCode::CURSOR_OUT_OF_ORDER);
});

it('processes multiple batches sequentially for large groups', function (): void {
    for ($i = 0; $i < 51; $i++) {
        bulkEnroll($this->course, $this->group);
    }

    $operationId = (string) Str::uuid();
    $max = GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX;

    $batch1 = $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY, 0, $max - 1, $operationId),
    )->assertSuccessful();

    expect($batch1->json('data.batch.has_more'))->toBeTrue();

    $batch2 = $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY, $max, $max, $operationId),
    )->assertSuccessful();

    expect($batch2->json('data.batch.has_more'))->toBeFalse()
        ->and(StudentAttendanceCard::query()->active()->count())->toBe(51);
});

it('skips online enrollments in snapshot', function (): void {
    bulkEnroll($this->course, $this->group, Course::ATTENDANCE_TYPE_ONLINE);

    $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY, 0, 0),
    )
        ->assertSuccessful()
        ->assertJsonPath('data.operation.snapshot_count', 0);
});

it('stores no token fields on bulk operation tables', function (): void {
    bulkEnroll($this->course, $this->group);

    $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY, 0, 0),
    )->assertSuccessful();

    $opsJson = json_encode(DB::table('group_attendance_card_bulk_operations')->get());
    $studentsJson = json_encode(DB::table('group_attendance_card_bulk_operation_students')->get());

    expect($opsJson)->not->toMatch('/card_[0-9a-f]{64}/')
        ->and($studentsJson)->not->toContain('token_hash')
        ->and($studentsJson)->not->toContain('plain_text_token');
});

it('cleans completed operations without deleting cards', function (): void {
    $student = bulkEnroll($this->course, $this->group);

    $this->postJson(
        bulkBatchUrl($this->group),
        bulkPayload(GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY, 0, 0),
    )->assertSuccessful();

    $operation = GroupAttendanceCardBulkOperation::query()->first();
    $operation->update(['completed_at' => now()->subDays(31)]);

    $this->artisan('attendance-cards:cleanup-bulk-operations', ['--days' => 30])
        ->assertSuccessful();

    expect(GroupAttendanceCardBulkOperation::query()->count())->toBe(0)
        ->and(StudentAttendanceCard::query()->where('student_id', $student->id)->count())->toBe(1);
});
