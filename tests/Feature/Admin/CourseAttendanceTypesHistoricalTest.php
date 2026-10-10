<?php

/**
 * Phase 6 — Historical enrollment attendance_type vs current course configuration.
 */

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);

    $this->category = Category::factory()->create(['status' => 'active']);
    $this->instructor = Instructor::factory()->create();
    $this->student = Student::factory()->create();
});

function historicalCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge([
        'category_id' => test()->category->id,
        'instructor_id' => test()->instructor->id,
        'status' => 'published',
        'published_at' => now(),
        'attendance_types' => ['online', 'offline'],
    ], $overrides));
}

function historicalEnrollment(Course $course, Student $student, string $attendanceType): Enrollment
{
    $order = Order::create([
        'student_id' => $student->id,
        'total_amount' => $course->price,
        'status' => 'completed',
        'billing_name' => 'Historical Billing',
        'billing_email' => 'historical@test.com',
        'billing_phone' => '01000000000',
    ]);

    return Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'price_paid' => $course->price,
        'attendance_type' => $attendanceType,
        'is_completed' => false,
    ]);
}

it('allows removing a currently supported type from a course when historical enrollments use it', function (): void {
    $course = historicalCourse(['attendance_types' => ['online', 'offline']]);
    $enrollment = historicalEnrollment($course, $this->student, 'offline');

    $this->putJson("/api/admin/courses/{$course->id}", [
        'attendance_types' => ['online'],
    ])->assertOk();

    expect($course->fresh()->attendance_types)->toBe(['online'])
        ->and($enrollment->fresh()->attendance_type)->toBe('offline');
});

it('preserves historical enrollment attendance type after course type reduction', function (): void {
    $course = historicalCourse(['attendance_types' => ['online', 'offline', 'hybrid']]);
    $enrollment = historicalEnrollment($course, $this->student, 'hybrid');

    $this->putJson("/api/admin/courses/{$course->id}", [
        'attendance_types' => ['online'],
    ])->assertOk();

    expect($enrollment->fresh()->attendance_type)->toBe('hybrid');
});

it('rejects new admin payment store with unsupported attendance type for current course config', function (): void {
    $course = historicalCourse(['attendance_types' => ['online']]);

    $this->postJson('/api/admin/payments', [
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'attendance_type' => 'offline',
        'billing_name' => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_type']);

    expect(Enrollment::count())->toBe(0);
});

it('accepts new admin payment store with supported attendance type for current course config', function (): void {
    $course = historicalCourse(['attendance_types' => ['online']]);

    $this->postJson('/api/admin/payments', [
        'student_id' => $this->student->id,
        'course_id' => $course->id,
        'attendance_type' => 'online',
        'billing_name' => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ])->assertCreated();

    expect(Enrollment::query()->value('attendance_type'))->toBe('online');
});
