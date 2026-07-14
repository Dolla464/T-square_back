<?php

/**
 * Feature tests for receptionist access to students, payments, and learning groups.
 */

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
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

    Role::create(['name' => 'receptionist', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->receptionist = User::factory()->create(['role' => 'receptionist']);
    $this->receptionist->assignRole('receptionist');
    Sanctum::actingAs($this->receptionist, ['*']);

    $this->instructor = Instructor::factory()->create();
    $this->course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
    ]);
});

function createReceptionistOrder(Student $student, Course $course): Order
{
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
        'price_paid' => 500,
        'is_completed' => false,
    ]);

    return $order;
}

it('allows receptionist to list students', function (): void {
    Student::factory()->count(2)->create();

    $this->getJson('/api/receptionist/students')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

it('allows receptionist to register a student with created_by receptionist', function (): void {
    $response = $this->postJson('/api/receptionist/users', [
        'full_name' => 'Receptionist Student Name',
        'email' => 'receptionist-student@example.com',
        'password' => 'Password123',
        'phone' => '01012345678',
        'role' => 'student',
        'gender' => 'male',
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('students', [
        'created_by' => 'receptionist',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'receptionist-student@example.com',
    ]);

    expect(User::where('email', 'receptionist-student@example.com')->value('email_verified_at'))
        ->not->toBeNull();
});

it('allows receptionist to list payments', function (): void {
    $student = Student::factory()->create();
    createReceptionistOrder($student, $this->course);

    $this->getJson('/api/receptionist/payments')
        ->assertOk();
});

it('forbids receptionist from exporting payments', function (): void {
    $this->getJson('/api/receptionist/payments/export')
        ->assertForbidden();
});

it('forbids receptionist from deleting payments', function (): void {
    $student = Student::factory()->create();
    $order = createReceptionistOrder($student, $this->course);

    $this->deleteJson("/api/receptionist/payments/{$order->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('orders', ['id' => $order->id]);
});

it('allows receptionist to list learning groups', function (): void {
    LearningGroup::create([
        'group_name' => 'Receptionist Group',
        'course_id' => $this->course->id,
        'course_instructor_id' => courseInstructorIdFor($this->course, $this->instructor),
        'enrolled_students' => 0,
    ]);

    $this->getJson('/api/receptionist/learning-groups')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

it('allows receptionist to read courses and instructors for forms', function (): void {
    $this->getJson('/api/receptionist/courses')
        ->assertOk();

    $this->getJson('/api/receptionist/instructors')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});
