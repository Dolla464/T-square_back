<?php

use App\Models\Certificate;
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

    $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole($adminRole);
    Sanctum::actingAs($this->admin, ['*']);

    $this->instructor = Instructor::factory()->create();

    $this->course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
        'duration_weeks' => 4,
    ]);
});

function createInvariantGroup(
    Course $course,
    Instructor $instructor,
    string $status = 'active',
): LearningGroup {
    return LearningGroup::create([
        'group_name' => 'Invariant Test Batch',
        'course_id' => $course->id,
        'course_instructor_id' => courseInstructorIdFor($course, $instructor),
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => $status,
        'enrolled_students' => 0,
    ]);
}

function createInvariantEnrollment(
    Course $course,
    ?LearningGroup $group = null,
    bool $isCompleted = false,
): Enrollment {
    $student = Student::factory()->create();

    $order = Order::create([
        'student_id' => $student->id,
        'total_amount' => 500,
        'status' => 'completed',
        'billing_name' => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);

    return Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $group?->id,
        'price_paid' => 500,
        'is_completed' => $isCompleted,
        'completed_at' => $isCompleted ? now() : null,
    ]);
}

function groupInvariantUpdatePayload(
    LearningGroup $group,
    Course $course,
    Instructor $instructor,
    array $overrides = [],
): array {
    return array_merge([
        'group_name' => $group->group_name,
        'course_id' => $course->id,
        'course_instructor_id' => courseInstructorIdFor($course, $instructor),
        'start_date' => $group->start_date?->format('Y-m-d') ?? now()->toDateString(),
        'status' => $group->status,
        'student_ids' => [],
        'student_statuses' => [],
    ], $overrides);
}

function updateStudentCourseStatus(Enrollment $enrollment, bool $isCompleted)
{
    return test()->putJson(
        "/api/admin/students/{$enrollment->student_id}/courses/{$enrollment->course_id}/status",
        ['is_completed' => $isCompleted]
    );
}

// ─── AdminStudentService ─────────────────────────────────────────────────────

it('rejects AdminStudentService completion when the learning group is active', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'active');
    $enrollment = createInvariantEnrollment($this->course, $group);

    updateStudentCourseStatus($enrollment, true)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_completed']);

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
    expect($enrollment->completed_at)->toBeNull();
});

it('allows AdminStudentService completion when the learning group is completed', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'completed');
    $enrollment = createInvariantEnrollment($this->course, $group);

    updateStudentCourseStatus($enrollment, true)
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeTrue();
    expect($enrollment->completed_at)->not->toBeNull();
});

it('rejects AdminStudentService completion when the learning group is cancelled', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'cancelled');
    $enrollment = createInvariantEnrollment($this->course, $group);

    updateStudentCourseStatus($enrollment, true)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_completed']);

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
});

it('rejects AdminStudentService completion when group_id is null', function (): void {
    $enrollment = createInvariantEnrollment($this->course, null);

    updateStudentCourseStatus($enrollment, true)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_completed']);

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
    expect($enrollment->group_id)->toBeNull();
});

// ─── syncGroupStudents ───────────────────────────────────────────────────────

it('rejects syncGroupStudents completion while the group is active', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'active');
    $enrollment = createInvariantEnrollment($this->course, $group);

    $this->putJson(
        "/api/admin/learning-groups/{$group->id}",
        groupInvariantUpdatePayload($group, $this->course, $this->instructor, [
            'status' => 'active',
            'student_ids' => [$enrollment->student_id],
            'student_statuses' => [(string) $enrollment->student_id => true],
        ])
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_completed']);

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
});

it('allows syncGroupStudents completion when the group is completed', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'completed');
    $enrollment = createInvariantEnrollment($this->course, $group);

    $this->putJson(
        "/api/admin/learning-groups/{$group->id}",
        groupInvariantUpdatePayload($group, $this->course, $this->instructor, [
            'status' => 'completed',
            'student_ids' => [$enrollment->student_id],
            'student_statuses' => [(string) $enrollment->student_id => true],
        ])
    )->assertOk();

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeTrue();
    expect($enrollment->completed_at)->not->toBeNull();
});

// ─── bulkCompleteStudents ────────────────────────────────────────────────────

it('rejects bulkCompleteStudents when the group is active', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'active');
    $enrollment = createInvariantEnrollment($this->course, $group);

    $this->postJson("/api/admin/learning-groups/{$group->id}/bulk-complete", [
        'student_ids' => [$enrollment->student_id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_completed']);

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
});

it('allows bulkCompleteStudents when the group is completed', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'completed');
    $enrollment = createInvariantEnrollment($this->course, $group);

    $this->postJson("/api/admin/learning-groups/{$group->id}/bulk-complete", [
        'student_ids' => [$enrollment->student_id],
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeTrue();
});

// ─── AdminCertificateService ─────────────────────────────────────────────────

it('rejects AdminCertificateService completion when the learning group is active', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'active');
    $enrollment = createInvariantEnrollment($this->course, $group);

    $certificate = Certificate::factory()->create([
        'student_id' => $enrollment->student_id,
        'course_id' => $enrollment->course_id,
    ]);

    $this->putJson("/api/admin/certificates/{$certificate->id}", [
        'is_completed' => true,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['is_completed']);

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
});

it('allows AdminCertificateService completion when the learning group is completed', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'completed');
    $enrollment = createInvariantEnrollment($this->course, $group);

    $certificate = Certificate::factory()->create([
        'student_id' => $enrollment->student_id,
        'course_id' => $enrollment->course_id,
    ]);

    $this->putJson("/api/admin/certificates/{$certificate->id}", [
        'is_completed' => true,
    ])->assertOk();

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeTrue();
    expect($enrollment->completed_at)->not->toBeNull();
});

// ─── Reopening / legacy / ordering ───────────────────────────────────────────

it('allows setting completion back to false through guarded admin paths', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'completed');
    $enrollment = createInvariantEnrollment($this->course, $group, true);

    updateStudentCourseStatus($enrollment, false)->assertOk();

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
    expect($enrollment->completed_at)->toBeNull();

    $certificate = Certificate::factory()->create([
        'student_id' => $enrollment->student_id,
        'course_id' => $enrollment->course_id,
    ]);

    $this->putJson("/api/admin/certificates/{$certificate->id}", [
        'is_completed' => false,
    ])->assertOk();

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeFalse();
});

it('does not disturb legacy completed enrollments on active groups', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'active');
    $enrollment = createInvariantEnrollment($this->course, $group, true);

    $loaded = Enrollment::query()->findOrFail($enrollment->id);

    expect($loaded->is_completed)->toBeTrue();
    expect($loaded->learningGroup->status)->toBe('active');
});

it('completes enrollments after the group status is updated to completed in the same request', function (): void {
    $group = createInvariantGroup($this->course, $this->instructor, 'active');
    $enrollment = createInvariantEnrollment($this->course, $group);

    $this->putJson(
        "/api/admin/learning-groups/{$group->id}",
        groupInvariantUpdatePayload($group, $this->course, $this->instructor, [
            'status' => 'completed',
            'student_ids' => [$enrollment->student_id],
            'student_statuses' => [(string) $enrollment->student_id => true],
        ])
    )->assertOk();

    $enrollment->refresh();

    expect($enrollment->is_completed)->toBeTrue();
    expect($enrollment->completed_at)->not->toBeNull();
    expect($group->fresh()->status)->toBe('completed');
});
