<?php

/**
 * Feature tests for admin payment (order) deletion.
 *
 * DELETE /api/admin/payments/{id}
 */

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole($adminRole);
    Sanctum::actingAs($this->admin, ['*']);

    $this->course = Course::factory()->create([
        'total_students' => 1,
        'total_revenue' => 500,
    ]);

    $this->student = Student::factory()->create();
});

function createOrderWithEnrollment(
    Student $student,
    Course $course,
    string $status = 'completed',
    float $pricePaid = 500,
): object {
    $order = Order::create([
        'student_id' => $student->id,
        'total_amount' => $pricePaid,
        'status' => $status,
        'billing_name' => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);

    $enrollment = Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'price_paid' => $pricePaid,
        'is_completed' => false,
    ]);

    return (object) compact('order', 'enrollment');
}

it('deletes a completed order and its linked enrollment', function (): void {
    $data = createOrderWithEnrollment($this->student, $this->course);

    $response = $this->deleteJson("/api/admin/payments/{$data->order->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('orders', ['id' => $data->order->id]);
    $this->assertDatabaseMissing('enrollments', ['id' => $data->enrollment->id]);
});

it('decrements course stats when deleting a completed order', function (): void {
    $data = createOrderWithEnrollment($this->student, $this->course);

    $this->deleteJson("/api/admin/payments/{$data->order->id}")
        ->assertNoContent();

    $this->course->refresh();

    expect($this->course->total_students)->toBe(0);
    expect((float) $this->course->total_revenue)->toBe(0.0);
});

it('does not decrement course stats when deleting a pending order', function (): void {
    $data = createOrderWithEnrollment($this->student, $this->course, 'pending');

    $this->deleteJson("/api/admin/payments/{$data->order->id}")
        ->assertNoContent();

    $this->course->refresh();

    expect($this->course->total_students)->toBe(1);
    expect((float) $this->course->total_revenue)->toBe(500.0);
});

it('keeps certificates when order and enrollment are deleted', function (): void {
    $data = createOrderWithEnrollment($this->student, $this->course);

    $certificate = Certificate::factory()->create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
    ]);

    $this->deleteJson("/api/admin/payments/{$data->order->id}")
        ->assertNoContent();

    $this->assertDatabaseHas('certificates', ['id' => $certificate->id]);
    $this->assertDatabaseMissing('enrollments', ['id' => $data->enrollment->id]);
});

it('returns 404 when deleting a non-existent order', function (): void {
    $this->deleteJson('/api/admin/payments/99999')
        ->assertNotFound();
});
