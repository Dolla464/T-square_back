<?php

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
});

function notificationStudent(): Student
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('student');

    $student = Student::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user, ['*']);

    return $student;
}

function notificationCourse(array $overrides = []): Course
{
    $category = Category::factory()->create(['status' => 'active']);
    $instructor = Instructor::factory()->create();

    return Course::factory()->create(array_merge([
        'category_id' => $category->id,
        'instructor_id' => $instructor->id,
        'status' => 'published',
        'published_at' => now(),
        'attendance_types' => ['online'],
    ], $overrides));
}

it('sends enrollment_pending notification for paid enrollment', function (): void {
    $student = notificationStudent();
    $course = notificationCourse([
        'is_free' => false,
        'price_before' => 500,
        'discount_price' => 0,
    ]);

    $this->postJson('/api/student/enrollments', [
        'course_id' => $course->id,
        'billing_name' => 'Test Student',
        'billing_email' => 'student@example.com',
        'billing_phone' => '01000000000',
    ])->assertCreated();

    $notification = $student->user->fresh()->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['type'])->toBe('enrollment_pending')
        ->and($notification->data['course_id'])->toBe($course->id)
        ->and($notification->data['course_title'])->toBe($course->title);
});

it('sends a single enrollment_confirmed notification for free enrollment', function (): void {
    $student = notificationStudent();
    $course = notificationCourse([
        'is_free' => true,
        'price_before' => 0,
        'discount_price' => 0,
    ]);

    $this->postJson('/api/student/enrollments', [
        'course_id' => $course->id,
    ])->assertCreated();

    $types = $student->user->fresh()->notifications()
        ->get()
        ->pluck('data.type')
        ->all();

    expect($types)->toBe(['enrollment_confirmed']);
});

it('sends enrollment_confirmed when pending order is completed', function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $student = notificationStudent();
    $course = notificationCourse([
        'is_free' => false,
        'price_before' => 500,
        'discount_price' => 0,
        'attendance_types' => ['online'],
    ]);

    $this->postJson('/api/student/enrollments', [
        'course_id' => $course->id,
        'billing_name' => 'Test Student',
        'billing_email' => 'student@example.com',
        'billing_phone' => '01000000000',
    ])->assertCreated();

    $order = Order::query()->latest('id')->first();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin, ['*']);

    $this->putJson("/api/admin/payments/{$order->id}", [
        'status' => 'completed',
        'attendance_type' => 'online',
    ])->assertOk();

    $types = $student->user->fresh()->notifications()
        ->get()
        ->pluck('data.type')
        ->all();

    expect($types)->toContain('enrollment_pending')
        ->and($types)->toContain('enrollment_confirmed')
        ->and(collect($types)->filter(fn (string $type) => $type === 'enrollment_confirmed')->count())->toBe(1);
});

it('does not send enrollment_pending for free enrollment', function (): void {
    $student = notificationStudent();
    $course = notificationCourse(['is_free' => true, 'price_before' => 0, 'discount_price' => 0]);

    $this->postJson('/api/student/enrollments', [
        'course_id' => $course->id,
    ])->assertCreated();

    $types = $student->user->fresh()->notifications()
        ->get()
        ->pluck('data.type')
        ->all();

    expect($types)->toContain('enrollment_confirmed')
        ->and($types)->not->toContain('enrollment_pending');
});

it('exposes pending course details with whatsapp link for pending enrollment', function (): void {
    Setting::query()->create([
        'key' => 'whatsapp',
        'value' => '01021327600',
        'type' => 'string',
        'group_name' => 'social',
    ]);

    $student = notificationStudent();
    $course = notificationCourse([
        'is_free' => false,
        'price_before' => 500,
        'discount_price' => 0,
    ]);

    $order = Order::query()->create([
        'student_id' => $student->id,
        'total_amount' => $course->price,
        'status' => 'pending',
        'billing_name' => 'Test Student',
        'billing_email' => 'student@example.com',
        'billing_phone' => '01000000000',
    ]);

    Enrollment::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'price_paid' => $course->price,
        'is_completed' => false,
    ]);

    $response = $this->getJson("/api/student/dashboard/courses/{$course->id}")->assertOk();

    expect($response->json('data.access_status'))->toBe('pending_payment')
        ->and($response->json('data.enrollment.status'))->toBe('pending_payment')
        ->and($response->json('data.whatsapp_link'))->toContain('wa.me/01021327600')
        ->and($response->json('data.lessons'))->toBeEmpty();
});

it('returns is_pending from check-enrollment endpoint', function (): void {
    $student = notificationStudent();
    $course = notificationCourse([
        'is_free' => false,
        'price_before' => 500,
        'discount_price' => 0,
    ]);

    $order = Order::query()->create([
        'student_id' => $student->id,
        'total_amount' => $course->price,
        'status' => 'pending',
        'billing_name' => 'Test Student',
        'billing_email' => 'student@example.com',
        'billing_phone' => '01000000000',
    ]);

    Enrollment::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'price_paid' => $course->price,
        'is_completed' => false,
    ]);

    $response = $this->getJson("/api/student/courses/{$course->id}/check-enrollment")->assertOk();

    expect($response->json('data.is_enrolled'))->toBeFalse()
        ->and($response->json('data.is_pending'))->toBeTrue()
        ->and($response->json('data.course_id'))->toBe($course->id);
});
