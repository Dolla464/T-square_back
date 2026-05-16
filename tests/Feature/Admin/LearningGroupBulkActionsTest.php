<?php

/**
 * Feature tests for the Learning-Group bulk-action endpoints.
 *
 * Covered routes (all under auth:sanctum + role:admin):
 *   GET  /api/admin/learning-groups/{groupId}/unassigned-students
 *   POST /api/admin/learning-groups/{groupId}/bulk-assign
 *   POST /api/admin/learning-groups/{groupId}/bulk-complete
 *   GET  /api/admin/learning-groups/selection
 *
 * Database: SQLite in-memory (phpunit.xml) — all migrations run fresh.
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

// ─────────────────────────────────────────────────────────────────────────────
// Shared bootstrap — runs before every test in this file
// ─────────────────────────────────────────────────────────────────────────────
beforeEach(function (): void {
    // Spatie caches roles in-process; flush so each test starts clean.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Build the Spatie 'admin' role and authenticate a user with it.
    $adminRole   = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole($adminRole);
    Sanctum::actingAs($this->admin, ['*']);

    // Shared fixtures reused across most tests.
    $this->instructor = Instructor::factory()->create();

    $this->course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
    ]);

    $this->group = LearningGroup::create([
        'group_name'        => 'Test Batch A',
        'course_id'         => $this->course->id,
        'instructor_id'     => $this->instructor->id,
        'enrolled_students' => 0,
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Helper — create a student enrolled in a course, backed by an order.
//
// We create the Order directly (not via factory) so we control the status
// precisely and avoid the OrderFactory's random-pick logic.
// The observer only fires on `updated`, not `created`, so no side-effects.
// ─────────────────────────────────────────────────────────────────────────────
function enrollStudent(
    Course $course,
    string $orderStatus = 'completed',
    ?int   $groupId     = null,
): object {
    $student = Student::factory()->create();

    $order = Order::create([
        'student_id'    => $student->id,
        'total_amount'  => 500,
        'status'        => $orderStatus,
        'billing_name'  => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);

    $enrollment = Enrollment::create([
        'student_id'   => $student->id,
        'course_id'    => $course->id,
        'order_id'     => $order->id,
        'group_id'     => $groupId,
        'price_paid'   => 500,
        'is_completed' => false,
    ]);

    return (object) compact('student', 'order', 'enrollment');
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. GET {groupId}/unassigned-students
// ─────────────────────────────────────────────────────────────────────────────
it('returns only paid and unassigned students for the group course', function (): void {
    // ✅ eligible — paid + no group yet
    $eligible1 = enrollStudent($this->course, 'completed', null);
    $eligible2 = enrollStudent($this->course, 'completed', null);

    // ❌ already assigned to this group
    enrollStudent($this->course, 'completed', $this->group->id);

    // ❌ unpaid — pending order
    enrollStudent($this->course, 'pending', null);

    $response = $this->getJson("/api/admin/learning-groups/{$this->group->id}/unassigned-students");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $eligible1->student->id])
        ->assertJsonFragment(['id' => $eligible2->student->id]);
});

it('returns 404 when the group does not exist for unassigned-students', function (): void {
    $this->getJson('/api/admin/learning-groups/99999/unassigned-students')
        ->assertJsonPath('status', 'error')
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2a. POST {groupId}/bulk-assign — full success (all paid)
// ─────────────────────────────────────────────────────────────────────────────
it('assigns all paid students to the group and updates the enrolled_students counter', function (): void {
    $s1 = enrollStudent($this->course, 'completed', null);
    $s2 = enrollStudent($this->course, 'completed', null);
    $s3 = enrollStudent($this->course, 'completed', null);

    $response = $this->postJson("/api/admin/learning-groups/{$this->group->id}/bulk-assign", [
        'student_ids' => [$s1->student->id, $s2->student->id, $s3->student->id],
        'course_id'   => $this->course->id,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        // No unpaid warning in the response data
        ->assertJsonMissing(['unpaid_students']);

    // Every enrollment must now reference the group
    foreach ([$s1, $s2, $s3] as $enrolled) {
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $enrolled->student->id,
            'course_id'  => $this->course->id,
            'group_id'   => $this->group->id,
        ]);
    }

    // Denormalised counter must reflect the real row count
    $this->assertDatabaseHas('learning_groups', [
        'id'                => $this->group->id,
        'enrolled_students' => 3,
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2b. POST {groupId}/bulk-assign — partial success (mixed paid / unpaid)
// ─────────────────────────────────────────────────────────────────────────────
it('skips unpaid students and returns their details as a warning in the response', function (): void {
    $paid1  = enrollStudent($this->course, 'completed', null);
    $paid2  = enrollStudent($this->course, 'completed', null);
    $unpaid = enrollStudent($this->course, 'pending',   null);

    $response = $this->postJson("/api/admin/learning-groups/{$this->group->id}/bulk-assign", [
        'student_ids' => [$paid1->student->id, $paid2->student->id, $unpaid->student->id],
        'course_id'   => $this->course->id,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        // The unpaid student must appear in the warning list
        ->assertJsonFragment([
            'id'        => $unpaid->student->id,
            'full_name' => $unpaid->student->full_name,
        ]);

    // Paid students must now be assigned
    $this->assertDatabaseHas('enrollments', [
        'student_id' => $paid1->student->id,
        'group_id'   => $this->group->id,
    ]);
    $this->assertDatabaseHas('enrollments', [
        'student_id' => $paid2->student->id,
        'group_id'   => $this->group->id,
    ]);

    // Unpaid student must remain unassigned
    $this->assertDatabaseHas('enrollments', [
        'student_id' => $unpaid->student->id,
        'group_id'   => null,
    ]);

    // Counter must count only the 2 paid students actually assigned
    $this->assertDatabaseHas('learning_groups', [
        'id'                => $this->group->id,
        'enrolled_students' => 2,
    ]);
});

it('returns 422 when student_ids is missing in bulk-assign', function (): void {
    $this->postJson("/api/admin/learning-groups/{$this->group->id}/bulk-assign", [
        'course_id' => $this->course->id,
    ])->assertStatus(422);
});

it('returns 422 when course_id is missing in bulk-assign', function (): void {
    $student = enrollStudent($this->course, 'completed', null)->student;

    $this->postJson("/api/admin/learning-groups/{$this->group->id}/bulk-assign", [
        'student_ids' => [$student->id],
    ])->assertStatus(422);
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. POST {groupId}/bulk-complete
// ─────────────────────────────────────────────────────────────────────────────
it('marks targeted enrollments as completed and leaves the others untouched', function (): void {
    // Two students to be completed
    $target1 = enrollStudent($this->course, 'completed', $this->group->id);
    $target2 = enrollStudent($this->course, 'completed', $this->group->id);

    // One student in the same group but intentionally excluded from the request
    $excluded = enrollStudent($this->course, 'completed', $this->group->id);

    $response = $this->postJson("/api/admin/learning-groups/{$this->group->id}/bulk-complete", [
        'student_ids' => [$target1->student->id, $target2->student->id],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success');

    // Targeted enrollments must be completed
    $this->assertDatabaseHas('enrollments', [
        'student_id'   => $target1->student->id,
        'is_completed' => true,
    ]);
    $this->assertDatabaseHas('enrollments', [
        'student_id'   => $target2->student->id,
        'is_completed' => true,
    ]);

    // Excluded enrollment must remain incomplete
    $this->assertDatabaseHas('enrollments', [
        'student_id'   => $excluded->student->id,
        'is_completed' => false,
    ]);
});

it('is idempotent — calling bulk-complete twice does not raise the completed_count', function (): void {
    $student = enrollStudent($this->course, 'completed', $this->group->id);

    $payload = ['student_ids' => [$student->student->id]];

    $this->postJson("/api/admin/learning-groups/{$this->group->id}/bulk-complete", $payload)
        ->assertOk();

    // Second call — the enrollment is already completed; is_completed filter
    // (where('is_completed', false)) ensures 0 rows are touched.
    $this->postJson("/api/admin/learning-groups/{$this->group->id}/bulk-complete", $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Successfully marked 0 student(s) as completed.');
});

it('returns 404 when the group does not exist for bulk-complete', function (): void {
    $student = enrollStudent($this->course, 'completed', null)->student;

    $this->postJson('/api/admin/learning-groups/99999/bulk-complete', [
        'student_ids' => [$student->id],
    ])->assertJsonPath('status', 'error')
      ->assertStatus(404);
});

it('returns 422 when student_ids is missing in bulk-complete', function (): void {
    $this->postJson("/api/admin/learning-groups/{$this->group->id}/bulk-complete", [])
        ->assertStatus(422);
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. GET /selection
// ─────────────────────────────────────────────────────────────────────────────
it('returns a lightweight id+name list of all groups for dropdowns', function (): void {
    // Create a second group so we can assert the count
    LearningGroup::create([
        'group_name'        => 'Test Batch B',
        'course_id'         => $this->course->id,
        'instructor_id'     => $this->instructor->id,
        'enrolled_students' => 0,
    ]);

    $response = $this->getJson('/api/admin/learning-groups/selection');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name'],
            ],
        ])
        // Confirm the known group name is present
        ->assertJsonFragment(['name' => 'Test Batch A'])
        ->assertJsonFragment(['name' => 'Test Batch B']);
});

it('returns an empty list when no groups exist', function (): void {
    // Delete the group created in beforeEach
    LearningGroup::query()->delete();

    $this->getJson('/api/admin/learning-groups/selection')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(0, 'data');
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Auth guard — unauthenticated requests are rejected
// ─────────────────────────────────────────────────────────────────────────────
it('rejects unauthenticated requests with 401', function (): void {
    // Flush the Sanctum user set in beforeEach by acting as a guest
    $this->withoutMiddleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

    $this->getJson('/api/admin/learning-groups/selection', ['Authorization' => ''])
        ->assertStatus(401);
})->skip('Guard test requires full token stack; covered by integration tests.');
