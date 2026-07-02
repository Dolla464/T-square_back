<?php

/**
 * Feature tests for learning-group status → enrollment sync.
 *
 * PUT /api/admin/learning-groups/{learningGroup}
 */

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CourseReviewRequired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole   = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole($adminRole);
    Sanctum::actingAs($this->admin, ['*']);

    $this->instructor = Instructor::factory()->create();

    $this->course = Course::factory()->create([
        'instructor_id' => $this->instructor->id,
        'duration_weeks' => 4,
    ]);

    $this->group = LearningGroup::create([
        'group_name'        => 'Status Sync Batch',
        'course_id'         => $this->course->id,
        'instructor_id'     => $this->instructor->id,
        'start_date'        => now()->toDateString(),
        'end_date'          => now()->addWeeks(4)->toDateString(),
        'status'            => 'active',
        'enrolled_students' => 0,
    ]);
});

function enrollStudentForStatusSync(
    Course $course,
    LearningGroup $group,
    bool $isCompleted = false,
): object {
    $student = Student::factory()->create();

    $order = Order::create([
        'student_id'    => $student->id,
        'total_amount'  => 500,
        'status'        => 'completed',
        'billing_name'  => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);

    $enrollment = Enrollment::create([
        'student_id'   => $student->id,
        'course_id'    => $course->id,
        'order_id'     => $order->id,
        'group_id'     => $group->id,
        'price_paid'   => 500,
        'is_completed' => $isCompleted,
        'completed_at' => $isCompleted ? now() : null,
    ]);

    return (object) compact('student', 'order', 'enrollment');
}

function groupUpdatePayload(LearningGroup $group, Course $course, Instructor $instructor, array $overrides = []): array
{
    return array_merge([
        'group_name'    => $group->group_name,
        'course_id'     => $course->id,
        'instructor_id' => $instructor->id,
        'start_date'    => $group->start_date?->format('Y-m-d') ?? now()->toDateString(),
        'status'        => $group->status,
        'student_ids'   => [],
        'student_statuses' => [],
    ], $overrides);
}

it('marks all group enrollments completed when status changes to completed', function (): void {
    Notification::fake();

    $s1 = enrollStudentForStatusSync($this->course, $this->group);
    $s2 = enrollStudentForStatusSync($this->course, $this->group);

    $response = $this->putJson(
        "/api/admin/learning-groups/{$this->group->id}",
        groupUpdatePayload($this->group, $this->course, $this->instructor, [
            'status' => 'completed',
            'student_ids' => [$s1->student->id, $s2->student->id],
            'student_statuses' => [
                (string) $s1->student->id => false,
                (string) $s2->student->id => false,
            ],
        ])
    );

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.sync.enrollments_completed', 2)
        ->assertJsonPath('data.sync.notifications_sent', 2);

    $this->assertDatabaseHas('enrollments', [
        'student_id'   => $s1->student->id,
        'is_completed' => true,
    ]);
    $this->assertDatabaseHas('enrollments', [
        'student_id'   => $s2->student->id,
        'is_completed' => true,
    ]);
    $this->assertDatabaseHas('learning_groups', [
        'id'     => $this->group->id,
        'status' => 'completed',
    ]);

    Notification::assertSentTo(
        $s1->student->user,
        CourseReviewRequired::class
    );
    Notification::assertSentTo(
        $s2->student->user,
        CourseReviewRequired::class
    );
});

it('reopens all group enrollments when status changes from completed to active', function (): void {
    Notification::fake();

    $this->group->update(['status' => 'completed']);

    $s1 = enrollStudentForStatusSync($this->course, $this->group, true);
    $s2 = enrollStudentForStatusSync($this->course, $this->group, true);

    $response = $this->putJson(
        "/api/admin/learning-groups/{$this->group->id}",
        groupUpdatePayload($this->group, $this->course, $this->instructor, [
            'status' => 'active',
            'student_ids' => [$s1->student->id, $s2->student->id],
            'student_statuses' => [
                (string) $s1->student->id => true,
                (string) $s2->student->id => true,
            ],
        ])
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.sync.enrollments_reopened', 2);

    $this->assertDatabaseHas('enrollments', [
        'student_id'   => $s1->student->id,
        'is_completed' => false,
    ]);
    $this->assertDatabaseHas('enrollments', [
        'student_id'   => $s2->student->id,
        'is_completed' => false,
    ]);
});

it('does not resend notifications when saving an already completed group', function (): void {
    Notification::fake();

    $this->group->update(['status' => 'completed']);

    $student = enrollStudentForStatusSync($this->course, $this->group, true);

    $this->putJson(
        "/api/admin/learning-groups/{$this->group->id}",
        groupUpdatePayload($this->group, $this->course, $this->instructor, [
            'status' => 'completed',
            'student_ids' => [$student->student->id],
            'student_statuses' => [(string) $student->student->id => true],
        ])
    )->assertOk()
        ->assertJsonPath('data.sync.enrollments_completed', 0)
        ->assertJsonPath('data.sync.notifications_sent', 0);

    Notification::assertNothingSent();
});

it('skips review notification when the student already submitted a review', function (): void {
    Notification::fake();

    $record = enrollStudentForStatusSync($this->course, $this->group);

    CourseReview::create([
        'course_id'         => $this->course->id,
        'student_id'        => $record->student->id,
        'instructor_id'     => $this->instructor->id,
        'content_rating'    => 4,
        'instructor_rating' => 4,
        'center_rating'     => 4,
        'overall_comment'   => 'Already reviewed',
    ]);

    $this->putJson(
        "/api/admin/learning-groups/{$this->group->id}",
        groupUpdatePayload($this->group, $this->course, $this->instructor, [
            'status' => 'completed',
            'student_ids' => [$record->student->id],
            'student_statuses' => [(string) $record->student->id => false],
        ])
    )->assertOk()
        ->assertJsonPath('data.sync.enrollments_completed', 1)
        ->assertJsonPath('data.sync.notifications_sent', 0);

    Notification::assertNothingSent();
});

it('does not change enrollments when status changes to cancelled', function (): void {
    Notification::fake();

    $record = enrollStudentForStatusSync($this->course, $this->group);

    $this->putJson(
        "/api/admin/learning-groups/{$this->group->id}",
        groupUpdatePayload($this->group, $this->course, $this->instructor, [
            'status' => 'cancelled',
            'student_ids' => [$record->student->id],
            'student_statuses' => [(string) $record->student->id => false],
        ])
    )->assertOk()
        ->assertJsonPath('data.sync.enrollments_completed', 0)
        ->assertJsonPath('data.sync.enrollments_reopened', 0);

    $this->assertDatabaseHas('enrollments', [
        'student_id'   => $record->student->id,
        'is_completed' => false,
    ]);
});

it('does not auto-issue certificates when a group is closed', function (): void {
    Notification::fake();

    $record = enrollStudentForStatusSync($this->course, $this->group);

    $this->putJson(
        "/api/admin/learning-groups/{$this->group->id}",
        groupUpdatePayload($this->group, $this->course, $this->instructor, [
            'status' => 'completed',
            'student_ids' => [$record->student->id],
            'student_statuses' => [(string) $record->student->id => false],
        ])
    )->assertOk();

    $this->assertDatabaseHas('enrollments', [
        'student_id'   => $record->student->id,
        'is_completed' => true,
    ]);

    expect(Certificate::query()->where('student_id', $record->student->id)->count())->toBe(0);
});
