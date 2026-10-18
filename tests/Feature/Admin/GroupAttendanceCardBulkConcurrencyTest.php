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
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Models\User;
use App\Services\Attendance\GroupAttendanceCardsPreviewService;
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
