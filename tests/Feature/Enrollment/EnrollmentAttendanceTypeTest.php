<?php

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const ENROLLMENT_ATTENDANCE_TYPE_MIGRATION = 'database/migrations/2026_09_28_130000_add_attendance_type_to_enrollments_table.php';

function rollbackEnrollmentAttendanceTypeMigration(): void
{
    Artisan::call('migrate:rollback', [
        '--path' => ENROLLMENT_ATTENDANCE_TYPE_MIGRATION,
    ]);
}

function runEnrollmentAttendanceTypeMigration(): void
{
    Artisan::call('migrate', [
        '--path' => ENROLLMENT_ATTENDANCE_TYPE_MIGRATION,
    ]);
}

function actingAsVerifiedStudent(): Student
{
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $user->assignRole('student');

    $student = Student::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user, ['*']);

    return $student;
}

function enrollmentCourse(array $courseOverrides = []): Course
{
    $category = Category::factory()->create(['status' => 'active']);
    $instructor = Instructor::factory()->create();

    return Course::factory()->create(array_merge([
        'category_id' => $category->id,
        'instructor_id' => $instructor->id,
        'status' => 'published',
        'published_at' => now(),
        'attendance_types' => ['online'],
    ], $courseOverrides));
}

function paidEnrollmentPayload(Course $course): array
{
    return [
        'course_id' => $course->id,
        'billing_name' => 'Test Student',
        'billing_email' => 'student@example.com',
        'billing_phone' => '01000000000',
    ];
}

it('adds a nullable attendance_type column to enrollments', function (): void {
    expect(Schema::hasColumn('enrollments', 'attendance_type'))->toBeTrue();

    $column = collect(Schema::getColumns('enrollments'))
        ->firstWhere('name', 'attendance_type');

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeTrue();
});

it('creates paid public enrollment with null attendance_type', function (): void {
    $student = actingAsVerifiedStudent();
    $course = enrollmentCourse([
        'is_free' => false,
        'price_before' => 500,
        'discount_price' => 0,
        'attendance_types' => ['online', 'offline'],
    ]);

    $response = $this->postJson('/api/student/enrollments', paidEnrollmentPayload($course))
        ->assertCreated();

    $enrollment = Enrollment::query()->latest('id')->first();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->student_id)->toBe($student->id)
        ->and($enrollment->attendance_type)->toBeNull()
        ->and($enrollment->order?->status)->toBe('pending')
        ->and($response->json('data.enrollment.attendance_type'))->toBeNull();
});

it('creates free public enrollment with null attendance_type even when order completes immediately', function (): void {
    $student = actingAsVerifiedStudent();
    $course = enrollmentCourse([
        'is_free' => true,
        'price_before' => 0,
        'discount_price' => 0,
        'attendance_types' => ['online'],
    ]);

    $response = $this->postJson('/api/student/enrollments', [
        'course_id' => $course->id,
    ])->assertCreated();

    $enrollment = Enrollment::query()->latest('id')->first();

    expect($enrollment->student_id)->toBe($student->id)
        ->and($enrollment->attendance_type)->toBeNull()
        ->and($enrollment->order?->status)->toBe('completed')
        ->and($response->json('data.enrollment.attendance_type'))->toBeNull();
});

it('rejects public enrollment requests that include attendance_type', function (string $attendanceType): void {
    $course = enrollmentCourse([
        'is_free' => false,
        'price_before' => 500,
        'discount_price' => 0,
    ]);

    actingAsVerifiedStudent();

    $this->postJson('/api/student/enrollments', array_merge(
        paidEnrollmentPayload($course),
        ['attendance_type' => $attendanceType],
    ))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_type']);

    expect(Enrollment::query()->count())->toBe(0);
})->with(['online', 'offline', 'hybrid']);

it('persists supported attendance types when assigned internally', function (string $attendanceType): void {
    $enrollment = Enrollment::factory()->create([
        'attendance_type' => $attendanceType,
    ]);

    expect($enrollment->fresh()->attendance_type)->toBe($attendanceType);
})->with(['online', 'offline', 'hybrid']);

it('exposes enrollment attendance_type on the course dashboard resource', function (): void {
    $student = actingAsVerifiedStudent();
    $course = enrollmentCourse([
        'attendance_types' => ['online', 'offline'],
    ]);

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'status' => 'completed',
    ]);

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'attendance_type' => null,
    ]);

    $nullResponse = $this->getJson("/api/student/dashboard/courses/{$course->id}")->assertOk();

    expect($nullResponse->json('data.enrollment.attendance_type'))->toBeNull();

    Enrollment::query()->where('student_id', $student->id)->update(['attendance_type' => 'offline']);

    $offlineResponse = $this->getJson("/api/student/dashboard/courses/{$course->id}")->assertOk();

    expect($offlineResponse->json('data.enrollment.attendance_type'))->toBe('offline');
});

it('backfills enrollments when the course supports exactly one attendance type', function (array $attendanceTypes, string $expected): void {
    rollbackEnrollmentAttendanceTypeMigration();

    expect(Schema::hasColumn('enrollments', 'attendance_type'))->toBeFalse();

    $student = Student::factory()->create();
    $course = enrollmentCourse(['attendance_types' => $attendanceTypes]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'status' => 'completed',
    ]);

    $enrollment = Enrollment::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'price_paid' => 100,
        'is_completed' => false,
    ]);

    runEnrollmentAttendanceTypeMigration();

    expect($enrollment->fresh()->attendance_type)->toBe($expected);
})->with([
    [['online'], 'online'],
    [['offline'], 'offline'],
    [['hybrid'], 'hybrid'],
]);

it('leaves enrollment attendance_type null for multi-type courses during backfill', function (): void {
    rollbackEnrollmentAttendanceTypeMigration();

    $student = Student::factory()->create();
    $instructor = Instructor::factory()->create();
    $course = enrollmentCourse([
        'attendance_types' => ['online', 'offline'],
        'instructor_id' => $instructor->id,
    ]);
    $group = LearningGroup::factory()->create([
        'course_id' => $course->id,
    ]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'status' => 'completed',
    ]);

    $enrollment = Enrollment::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $group->id,
        'price_paid' => 100,
        'is_completed' => false,
    ]);

    runEnrollmentAttendanceTypeMigration();

    expect($enrollment->fresh()->attendance_type)->toBeNull();
});

it('can rollback and re-run the enrollment attendance_type migration', function (): void {
    expect(Schema::hasColumn('enrollments', 'attendance_type'))->toBeTrue();

    rollbackEnrollmentAttendanceTypeMigration();

    expect(Schema::hasColumn('enrollments', 'attendance_type'))->toBeFalse();

    runEnrollmentAttendanceTypeMigration();

    expect(Schema::hasColumn('enrollments', 'attendance_type'))->toBeTrue();
});
