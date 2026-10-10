<?php

/**
 * Phase 4 — Admin/Receptionist payment attendance type assignment.
 */

use App\Events\CoursePurchased;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\Enrollment\OnlineEnrollmentCompletionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'receptionist', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);

    $this->instructor = Instructor::factory()->create();
    $this->category = Category::factory()->create(['status' => 'active']);
    $this->student = Student::factory()->create();
});

function paymentCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge([
        'category_id' => test()->category->id,
        'instructor_id' => test()->instructor->id,
        'status' => 'published',
        'published_at' => now(),
        'attendance_types' => ['online', 'offline'],
        'is_free' => false,
        'price_before' => 500,
        'discount_price' => 0,
    ], $overrides));
}

function adminStorePayload(Course $course, Student $student, array $overrides = []): array
{
    return array_merge([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'attendance_type' => 'online',
        'billing_name' => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ], $overrides);
}

function createPendingPublicEnrollment(Course $course, Student $student): array
{
    $order = Order::create([
        'student_id' => $student->id,
        'total_amount' => $course->price,
        'status' => 'pending',
        'billing_name' => 'Public Student',
        'billing_email' => 'public@test.com',
        'billing_phone' => '01000000001',
    ]);

    $enrollment = Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'price_paid' => $course->price,
        'attendance_type' => null,
        'is_completed' => false,
    ]);

    return [$order, $enrollment];
}

function createCompletedOrderWithEnrollment(
    Course $course,
    Student $student,
    ?string $attendanceType = 'online',
): array {
    $order = Order::create([
        'student_id' => $student->id,
        'total_amount' => $course->price,
        'status' => 'completed',
        'billing_name' => 'Completed Billing',
        'billing_email' => 'completed@test.com',
        'billing_phone' => '01000000002',
    ]);

    $enrollment = Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'price_paid' => $course->price,
        'attendance_type' => $attendanceType,
        'is_completed' => false,
    ]);

    return [$order, $enrollment];
}

it('requires attendance_type on admin store', function (): void {
    $course = paymentCourse();

    $this->postJson('/api/admin/payments', [
        'student_id' => $this->student->id,
        'course_id' => $course->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_type']);

    expect(Order::count())->toBe(0)
        ->and(Enrollment::count())->toBe(0);
});

it('accepts a supported attendance type on admin store', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);

    $response = $this->postJson('/api/admin/payments', adminStorePayload($course, $this->student, [
        'attendance_type' => 'offline',
    ]))->assertCreated();

    $order = Order::query()->first();
    $enrollment = Enrollment::query()->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe('completed')
        ->and($enrollment)->not->toBeNull()
        ->and($enrollment->attendance_type)->toBe('offline')
        ->and($response->json('data')['enrollments.attendance_type'])->toBe('offline');
});

it('rejects an unsupported attendance type on admin store', function (): void {
    $course = paymentCourse(['attendance_types' => ['online']]);

    $this->postJson('/api/admin/payments', adminStorePayload($course, $this->student, [
        'attendance_type' => 'offline',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_type']);

    expect(Order::where('status', 'completed')->count())->toBe(0)
        ->and(Enrollment::count())->toBe(0);
});

it('accepts each supported attendance type on admin store', function (string $attendanceType, array $courseTypes): void {
    $course = paymentCourse(['attendance_types' => $courseTypes]);

    $this->postJson('/api/admin/payments', adminStorePayload($course, $this->student, [
        'attendance_type' => $attendanceType,
    ]))->assertCreated();

    expect(Enrollment::query()->value('attendance_type'))->toBe($attendanceType);
})->with([
    'online' => ['online', ['online']],
    'offline' => ['offline', ['offline']],
    'hybrid' => ['hybrid', ['hybrid']],
]);

it('requires attendance_type on admin store for free courses', function (): void {
    $course = paymentCourse([
        'is_free' => true,
        'price_before' => 0,
        'discount_price' => 0,
        'attendance_types' => ['online'],
    ]);

    $this->postJson('/api/admin/payments', [
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'billing_name' => 'Free Course Billing',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_type']);

    $this->postJson('/api/admin/payments', adminStorePayload($course, $this->student, [
        'attendance_type' => 'online',
    ]))->assertCreated();

    expect(Enrollment::query()->value('attendance_type'))->toBe('online');
});

it('completes a pending public order and assigns attendance type', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order, $enrollment] = createPendingPublicEnrollment($course, $this->student);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'offline',
    ])->assertOk();

    expect($order->fresh()->status)->toBe('completed')
        ->and($enrollment->fresh()->attendance_type)->toBe('offline');
});

it('sets status_changed_at when completing a pending order through admin payment update', function (): void {
    Carbon::setTestNow('2026-03-01 10:00:00');

    $course = paymentCourse([
        'attendance_types' => ['online', 'offline'],
        'duration_weeks' => 4,
    ]);
    [$order, $enrollment] = createPendingPublicEnrollment($course, $this->student);

    $pendingStatusChangedAt = $order->fresh()->status_changed_at;
    expect($pendingStatusChangedAt)->not->toBeNull();

    Carbon::setTestNow('2026-03-01 12:00:00');

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'online',
    ])->assertOk();

    $order->refresh();
    $enrollment->refresh();

    expect($order->status)->toBe('completed')
        ->and($order->status_changed_at)->not->toBeNull()
        ->and($order->status_changed_at->greaterThan($pendingStatusChangedAt))->toBeTrue()
        ->and($enrollment->attendance_type)->toBe('online');

    Carbon::setTestNow('2026-05-01 12:00:00');

    expect(app(OnlineEnrollmentCompletionService::class)->isEligibleForAutoCompletion($enrollment->fresh()))->toBeTrue();
});

it('rejects pending to completed without attendance type', function (): void {
    $course = paymentCourse();
    [$order, $enrollment] = createPendingPublicEnrollment($course, $this->student);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_type']);

    expect($order->fresh()->status)->toBe('pending')
        ->and($enrollment->fresh()->attendance_type)->toBeNull();
});

it('rejects pending to completed with unsupported attendance type', function (): void {
    $course = paymentCourse(['attendance_types' => ['online']]);
    [$order] = createPendingPublicEnrollment($course, $this->student);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'offline',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_type']);

    expect($order->fresh()->status)->toBe('pending');
});

it('completes pending order without course_id or student_id in payload', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order, $enrollment] = createPendingPublicEnrollment($course, $this->student);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'online',
    ])->assertOk();

    expect($order->fresh()->status)->toBe('completed')
        ->and($enrollment->fresh()->attendance_type)->toBe('online');
});

it('assigns attendance type when enrollment is null', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order, $enrollment] = createPendingPublicEnrollment($course, $this->student);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'offline',
    ])->assertOk();

    expect($enrollment->fresh()->attendance_type)->toBe('offline');
});

it('allows idempotent completion when attendance type matches existing value', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order, $enrollment] = createPendingPublicEnrollment($course, $this->student);
    $enrollment->update(['attendance_type' => 'offline']);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'offline',
    ])->assertOk();

    expect(Enrollment::count())->toBe(1)
        ->and($enrollment->fresh()->attendance_type)->toBe('offline');
});

it('rejects completion when attendance type differs from existing value', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order, $enrollment] = createPendingPublicEnrollment($course, $this->student);
    $enrollment->update(['attendance_type' => 'online']);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'offline',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_type']);

    expect($order->fresh()->status)->toBe('pending')
        ->and($enrollment->fresh()->attendance_type)->toBe('online');
});

it('cancels a completed order without changing attendance type', function (): void {
    $course = paymentCourse();
    [$order, $enrollment] = createCompletedOrderWithEnrollment($course, $this->student, 'online');

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'cancelled',
    ])->assertOk();

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($enrollment->fresh()->attendance_type)->toBe('online');
});

it('refunds a completed order without changing attendance type', function (): void {
    $course = paymentCourse();
    [$order, $enrollment] = createCompletedOrderWithEnrollment($course, $this->student, 'offline');

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'refunded',
    ])->assertOk();

    expect($order->fresh()->status)->toBe('refunded')
        ->and($enrollment->fresh()->attendance_type)->toBe('offline');
});

it('rejects attendance_type when cancelling an order', function (): void {
    $course = paymentCourse();
    [$order, $enrollment] = createCompletedOrderWithEnrollment($course, $this->student, 'online');

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'cancelled',
        'attendance_type' => 'offline',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_type']);

    expect($order->fresh()->status)->toBe('completed')
        ->and($enrollment->fresh()->attendance_type)->toBe('online');
});

it('rejects attendance_type when refunding an order', function (): void {
    $course = paymentCourse();
    [$order, $enrollment] = createCompletedOrderWithEnrollment($course, $this->student, 'online');

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'refunded',
        'attendance_type' => 'offline',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_type']);

    expect($order->fresh()->status)->toBe('completed')
        ->and($enrollment->fresh()->attendance_type)->toBe('online');
});

it('does not require attendance_type when updating an already completed order', function (): void {
    $course = paymentCourse();
    [$order, $enrollment] = createCompletedOrderWithEnrollment($course, $this->student, 'online');

    Event::fake([CoursePurchased::class]);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'billing_name' => 'Updated Billing Name',
    ])->assertOk();

    Event::assertNotDispatched(CoursePurchased::class);

    expect($order->fresh()->status)->toBe('completed')
        ->and($order->fresh()->billing_name)->toBe('Updated Billing Name')
        ->and($enrollment->fresh()->attendance_type)->toBe('online')
        ->and(Enrollment::count())->toBe(1);
});

it('does not dispatch CoursePurchased again on repeated completion', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order, $enrollment] = createPendingPublicEnrollment($course, $this->student);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'online',
    ])->assertOk();

    Event::fake([CoursePurchased::class]);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
    ])->assertOk();

    Event::assertNotDispatched(CoursePurchased::class);
    expect($enrollment->fresh()->attendance_type)->toBe('online');
});

it('rejects completing an order without an enrollment', function (): void {
    $order = Order::create([
        'student_id' => $this->student->id,
        'total_amount' => 500,
        'status' => 'pending',
        'billing_name' => 'No Enrollment',
        'billing_email' => 'no-enrollment@test.com',
        'billing_phone' => '01000000003',
    ]);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'online',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect($order->fresh()->status)->toBe('pending');
});

it('allows receptionist store with attendance_type', function (): void {
    $receptionist = User::factory()->create(['role' => 'receptionist']);
    $receptionist->assignRole('receptionist');
    Sanctum::actingAs($receptionist, ['*']);

    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);

    $this->postJson('/api/receptionist/payments', adminStorePayload($course, $this->student, [
        'attendance_type' => 'offline',
    ]))->assertCreated();

    expect(Enrollment::query()->value('attendance_type'))->toBe('offline');
});

it('allows receptionist to complete pending order with attendance_type', function (): void {
    $receptionist = User::factory()->create(['role' => 'receptionist']);
    $receptionist->assignRole('receptionist');
    Sanctum::actingAs($receptionist, ['*']);

    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order, $enrollment] = createPendingPublicEnrollment($course, $this->student);

    $this->patchJson("/api/receptionist/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'offline',
    ])->assertOk();

    expect($order->fresh()->status)->toBe('completed')
        ->and($enrollment->fresh()->attendance_type)->toBe('offline');
});

it('forbids unauthorized users from admin payment store', function (): void {
    $studentUser = User::factory()->create();
    $studentUser->assignRole('student');
    Sanctum::actingAs($studentUser, ['*']);

    $course = paymentCourse();

    $this->postJson('/api/admin/payments', adminStorePayload($course, $this->student))
        ->assertForbidden();
});

it('does not leave order completed when attendance assignment fails', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order, $enrollment] = createPendingPublicEnrollment($course, $this->student);
    $enrollment->update(['attendance_type' => 'online']);

    $this->patchJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'offline',
    ])->assertUnprocessable();

    expect($order->fresh()->status)->toBe('pending')
        ->and($enrollment->fresh()->attendance_type)->toBe('online');
});

it('exposes enrollment attendance_type and course attendance_types independently in payment response', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);

    $response = $this->postJson('/api/admin/payments', adminStorePayload($course, $this->student, [
        'attendance_type' => 'offline',
    ]))->assertCreated();

    $payload = $response->json('data');

    expect($payload['enrollments.attendance_type'])->toBe('offline')
        ->and($payload['enrollments.course.attendance_types'])->toBe(['online', 'offline'])
        ->and($payload)->not->toHaveKey('attendance_types');
});

it('filters admin payment list by enrollment attendance type', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline', 'hybrid']]);
    [$onlineOrder] = createCompletedOrderWithEnrollment($course, $this->student, 'online');
    [$offlineOrder] = createCompletedOrderWithEnrollment($course, Student::factory()->create(), 'offline');
    [$pendingOrder, $pendingEnrollment] = createPendingPublicEnrollment($course, Student::factory()->create());

    expect($pendingEnrollment->attendance_type)->toBeNull();

    $onlineResponse = $this->getJson('/api/admin/payments?attendance_type=online')->assertOk();
    $onlineIds = collect($onlineResponse->json('data.orders'))->pluck('id');

    expect($onlineIds)->toContain($onlineOrder->id)
        ->and($onlineIds)->not->toContain($offlineOrder->id)
        ->and($onlineIds)->not->toContain($pendingOrder->id);

    $unassignedResponse = $this->getJson('/api/admin/payments?attendance_type=unassigned')->assertOk();
    $unassignedIds = collect($unassignedResponse->json('data.orders'))->pluck('id');

    expect($unassignedIds)->toContain($pendingOrder->id)
        ->and($unassignedIds)->not->toContain($onlineOrder->id)
        ->and($unassignedIds)->not->toContain($offlineOrder->id);
});

it('exposes course attendance_types on admin payment list', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order] = createCompletedOrderWithEnrollment($course, $this->student, 'offline');

    $response = $this->getJson('/api/admin/payments')->assertOk();

    $item = collect($response->json('data.orders'))->firstWhere('id', $order->id);

    expect($item)->not->toBeNull()
        ->and($item['enrollments.attendance_type'])->toBe('offline')
        ->and($item['enrollments.course.attendance_types'])->toBe(['online', 'offline']);
});

it('exposes course attendance_types on receptionist payment list', function (): void {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('receptionist');

    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order] = createCompletedOrderWithEnrollment($course, $this->student, 'offline');

    Sanctum::actingAs($receptionist, ['*']);

    $response = $this->getJson('/api/receptionist/payments')->assertOk();

    $item = collect($response->json('data.orders'))->firstWhere('id', $order->id);

    expect($item)->not->toBeNull()
        ->and($item['enrollments.attendance_type'])->toBe('offline')
        ->and($item['enrollments.course.attendance_types'])->toBe(['online', 'offline']);
});

it('exposes course attendance_types on admin payment detail', function (): void {
    $course = paymentCourse(['attendance_types' => ['online', 'offline']]);
    [$order] = createCompletedOrderWithEnrollment($course, $this->student, 'offline');

    $payload = $this->getJson("/api/admin/payments/{$order->id}")
        ->assertOk()
        ->json('data');

    expect($payload['enrollments.attendance_type'])->toBe('offline')
        ->and($payload['enrollments.course.attendance_types'])->toBe(['online', 'offline']);
});
