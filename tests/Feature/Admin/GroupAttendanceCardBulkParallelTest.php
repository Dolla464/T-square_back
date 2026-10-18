<?php

/**
 * True parallel HTTP workers (separate PHP processes) for bulk attendance cards.
 *
 * Run on t_square_lms_test only:
 *   vendor/bin/pest tests/Feature/Admin/GroupAttendanceCardBulkParallelTest.php -c phpunit.mysql-concurrency.xml --processes=1
 */

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GroupAttendanceCardBulkOperation;
use App\Models\Order;
use App\Models\GroupAttendanceCardBulkOperationStudent;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Models\User;
use App\Services\Attendance\GroupAttendanceCardBulkResultCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BulkConcurrency\ParallelHttpRunner;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (config('database.default') !== 'mysql') {
        $this->markTestSkipped('MySQL parallel suite requires DB_CONNECTION=mysql (see phpunit.mysql-concurrency.xml).');
    }

    $database = (string) config('database.connections.mysql.database');
    if (! preg_match('/test/i', $database)) {
        $this->markTestSkipped('Refusing MySQL parallel tests on non-test database: '.$database);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);

    $this->instructor = Instructor::factory()->create();
    $this->course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    $this->group = LearningGroup::create([
        'group_name' => 'Parallel Bulk Group',
        'course_id' => $this->course->id,
        'course_instructor_id' => courseInstructorIdFor($this->course, $this->instructor),
        'enrolled_students' => 0,
    ]);
});

function parallelBulkEnroll(Course $course, LearningGroup $group): Student
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

function parallelBatchUrl(LearningGroup $group): string
{
    return '/api/admin/learning-groups/'.$group->id.'/attendance-cards/batch';
}

function parallelStatusUrl(LearningGroup $group, string $clientOperationId): string
{
    return '/api/admin/learning-groups/'.$group->id.'/attendance-cards/batch/'.$clientOperationId;
}

function parallelBatchPayload(string $clientOperationId): array
{
    return [
        'client_operation_id' => $clientOperationId,
        'mode' => GroupAttendanceCardBulkOperation::MODE_ISSUE_NEW_ONLY,
        'confirm_reissue' => false,
        'batch_cursor' => ['from_sequence' => 0, 'to_sequence' => 0],
    ];
}

function parallelCommitTestDataForWorkers(): void
{
    $connection = DB::connection();
    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }
}

function parallelResultByWorkerId(array $results, string $workerId): array
{
    foreach ($results as $result) {
        if (($result['worker_id'] ?? '') === $workerId) {
            return $result;
        }
    }

    throw new RuntimeException('Missing worker result: '.$workerId);
}

function parallelAssertStatusHasNoSecrets(string $url, string $token): void
{
    $response = test()->getJson($url, ['Authorization' => 'Bearer '.$token])->assertSuccessful();
    $json = json_encode($response->json());
    expect($json)->not->toContain('plain_text_token')
        ->and($json)->not->toContain('token_hash');
}

it('runs bulk and individual issue in parallel with at most one active card on mysql', function (): void {
    $student = parallelBulkEnroll($this->course, $this->group);
    $token = $this->admin->createToken('bulk-parallel-t1')->plainTextToken;
    $clientOperationId = (string) Str::uuid();
    $env = ParallelHttpRunner::testingEnvFromApp();

    parallelCommitTestDataForWorkers();

    $results = ParallelHttpRunner::run([
        [
            'worker_id' => 'bulk',
            'env' => $env,
            'request' => [
                'method' => 'POST',
                'uri' => parallelBatchUrl($this->group),
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
                'json' => parallelBatchPayload($clientOperationId),
            ],
        ],
        [
            'worker_id' => 'individual',
            'env' => $env,
            'request' => [
                'method' => 'POST',
                'uri' => '/api/admin/students/'.$student->id.'/attendance-card',
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
            ],
        ],
    ]);

    expect(count(array_unique(array_column($results, 'pid'))))->toBe(2);

    $bulk = parallelResultByWorkerId($results, 'bulk');
    $individual = parallelResultByWorkerId($results, 'individual');

    expect($bulk['ok'])->toBeTrue()
        ->and($individual['ok'])->toBeTrue();

    $bulkStudent = $bulk['json']['data']['students'][0] ?? null;
    expect($bulkStudent)->toBeArray();

    $bulkSuccess = ($bulk['http_status'] ?? 0) === 200
        && ($bulkStudent['status'] ?? '') === GroupAttendanceCardBulkOperationStudent::STATUS_SUCCESS;
    $individualSuccess = ($individual['http_status'] ?? 0) === 201;

    expect($bulkSuccess || $individualSuccess)->toBeTrue();
    expect($bulkSuccess && $individualSuccess)->toBeFalse();

    $activeCards = StudentAttendanceCard::query()
        ->active()
        ->where('student_id', $student->id)
        ->get();

    expect($activeCards)->toHaveCount(1);

    $card = $activeCards->first();
    expect($card)->not->toBeNull();

    if ($bulkSuccess) {
        expect($individual['http_status'])->toBe(422)
            ->and($individual['json']['code'] ?? null)->toBe('CARD_ALREADY_ACTIVE');

        $operation = GroupAttendanceCardBulkOperation::query()
            ->where('client_operation_id', $clientOperationId)
            ->firstOrFail();

        expect($card->group_attendance_card_bulk_operation_id)->toBe($operation->id)
            ->and($bulkStudent['result_code'] ?? null)->toBe(GroupAttendanceCardBulkResultCode::ISSUED);
    } else {
        expect($individualSuccess)->toBeTrue()
            ->and($bulkStudent['status'] ?? null)->toBe(GroupAttendanceCardBulkOperationStudent::STATUS_SKIPPED)
            ->and($bulkStudent['result_code'] ?? null)->toBeIn([
                GroupAttendanceCardBulkResultCode::SKIP_ELIGIBILITY_CHANGED,
                GroupAttendanceCardBulkResultCode::SKIP_NO_ACTION_FOR_MODE,
            ])
            ->and($card->group_attendance_card_bulk_operation_id)->toBeNull();
    }

    if ($bulkSuccess) {
        expect($bulkStudent['plain_text_token'] ?? null)->not->toBeNull();
    }

    if ($individualSuccess) {
        $issuedToken = $individual['json']['data']['plain_text_token']
            ?? $individual['json']['plain_text_token']
            ?? null;
        expect($issuedToken)->toMatch('/^card_[0-9a-f]{64}$/');
    }

    parallelAssertStatusHasNoSecrets(parallelStatusUrl($this->group, $clientOperationId), $token);

    $replay = test()->postJson(
        parallelBatchUrl($this->group),
        parallelBatchPayload($clientOperationId),
        ['Authorization' => 'Bearer '.$token],
    )->assertSuccessful();

    $replayJson = json_encode($replay->json());
    expect($replayJson)->not->toContain('plain_text_token')
        ->and($replayJson)->not->toContain('token_hash');
});

it('allows only one parallel bulk batch to hold the group lock on mysql', function (): void {
    if (! Schema::hasTable('cache_locks')) {
        $this->markTestSkipped('cache_locks table is required for cross-process cache locks (run migrations on test DB).');
    }

    $student = parallelBulkEnroll($this->course, $this->group);
    expect($student)->not->toBeNull();

    $token = $this->admin->createToken('bulk-parallel-t2')->plainTextToken;
    $env = ParallelHttpRunner::testingEnvFromApp('database');

    parallelCommitTestDataForWorkers();

    $results = ParallelHttpRunner::run([
        [
            'worker_id' => 'bulk_a',
            'env' => $env,
            'expected_cache_store' => 'database',
            'request' => [
                'method' => 'POST',
                'uri' => parallelBatchUrl($this->group),
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
                'json' => parallelBatchPayload((string) Str::uuid()),
            ],
        ],
        [
            'worker_id' => 'bulk_b',
            'env' => $env,
            'expected_cache_store' => 'database',
            'request' => [
                'method' => 'POST',
                'uri' => parallelBatchUrl($this->group),
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                ],
                'json' => parallelBatchPayload((string) Str::uuid()),
            ],
        ],
    ]);

    expect(count(array_unique(array_column($results, 'pid'))))->toBe(2);

    foreach ($results as $result) {
        expect($result['ok'])->toBeTrue()
            ->and($result['meta']['cache_default'] ?? null)->toBe('database')
            ->and($result['meta']['cache_driver'] ?? null)->toBe('database');
    }

    $statusCodes = array_map(static fn (array $r): int => (int) ($r['http_status'] ?? 0), $results);
    expect($statusCodes)->toContain(200)
        ->and($statusCodes)->toContain(409);

    $conflict = null;
    $success = null;
    foreach ($results as $result) {
        if (($result['http_status'] ?? 0) === 409) {
            $conflict = $result;
        }
        if (($result['http_status'] ?? 0) === 200) {
            $success = $result;
        }
    }

    expect($conflict)->not->toBeNull()
        ->and($success)->not->toBeNull()
        ->and($conflict['json']['code'] ?? null)->toBe(GroupAttendanceCardBulkResultCode::GROUP_LOCK_UNAVAILABLE);

    expect(StudentAttendanceCard::query()->active()->where('student_id', $student->id)->count())->toBeLessThanOrEqual(1);

    if (($success['json']['data']['students'][0]['status'] ?? '') === 'success') {
        $operationId = $success['json']['data']['operation']['client_operation_id'] ?? null;
        expect($operationId)->not->toBeNull();

        $operation = GroupAttendanceCardBulkOperation::query()
            ->where('client_operation_id', $operationId)
            ->firstOrFail();

        $card = StudentAttendanceCard::query()->active()->where('student_id', $student->id)->first();
        if ($card !== null) {
            expect($card->group_attendance_card_bulk_operation_id)->toBe($operation->id);
        }

        parallelAssertStatusHasNoSecrets(parallelStatusUrl($this->group, (string) $operationId), $token);

        $replay = test()->postJson(
            parallelBatchUrl($this->group),
            parallelBatchPayload((string) $operationId),
            ['Authorization' => 'Bearer '.$token],
        )->assertSuccessful();

        $replayJson = json_encode($replay->json());
        expect($replayJson)->not->toContain('plain_text_token')
            ->and($replayJson)->not->toContain('token_hash');
    }
});
