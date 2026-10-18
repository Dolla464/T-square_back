<?php

/**
 * Feature tests for GET /api/admin/learning-groups/{learningGroup}/attendance-cards/preview
 *
 * Database: SQLite in-memory (phpunit.xml).
 */

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Models\User;
use App\Services\Attendance\GroupAttendanceCardsPreviewService;
use App\Services\Attendance\StudentAttendanceCardLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);

    $this->studentUser = User::factory()->create();
    $this->studentUser->assignRole('student');

    $this->instructor = Instructor::factory()->create();
    $this->course = Course::factory()->create(['instructor_id' => $this->instructor->id]);
    $this->group = LearningGroup::create([
        'group_name' => 'Preview Group',
        'course_id' => $this->course->id,
        'course_instructor_id' => courseInstructorIdFor($this->course, $this->instructor),
        'enrolled_students' => 0,
    ]);
});

function previewEnroll(
    Course $course,
    LearningGroup $group,
    ?string $attendanceType = 'offline',
    bool $isCompleted = false,
    string $studentStatus = 'active',
): object {
    $student = Student::factory()->create();
    if ($studentStatus !== 'active') {
        $student->forceFill(['status' => $studentStatus])->save();
    }

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
        'attendance_type' => $attendanceType,
        'price_paid' => 500,
        'is_completed' => $isCompleted,
        'completed_at' => $isCompleted ? now() : null,
    ]);

    return (object) compact('student', 'order');
}

function previewUrl(LearningGroup $group): string
{
    return '/api/admin/learning-groups/'.$group->id.'/attendance-cards/preview';
}

/**
 * @return array{enrollments: string, students: string, cards: string}
 */
function previewRelatedDbSnapshot(): array
{
    return [
        'enrollments' => json_encode(DB::table('enrollments')->orderBy('id')->get()->all()),
        'students' => json_encode(DB::table('students')->orderBy('id')->get()->all()),
        'cards' => json_encode(DB::table('student_attendance_cards')->orderBy('id')->get()->all()),
    ];
}

it('denies guest access to attendance cards preview', function (): void {
    auth()->guard('sanctum')->forgetUser();

    $this->getJson(previewUrl($this->group))->assertUnauthorized();
});

it('denies non-admin access to attendance cards preview', function (): void {
    Sanctum::actingAs($this->studentUser, ['*']);

    $this->getJson(previewUrl($this->group))->assertForbidden();
});

it('denies unverified admin email with 409 on preview', function (): void {
    $unverifiedAdmin = User::factory()->create();
    $unverifiedAdmin->forceFill(['email_verified_at' => null])->save();
    $unverifiedAdmin->assignRole('admin');
    Sanctum::actingAs($unverifiedAdmin, ['*']);

    $this->getJson(previewUrl($this->group))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Your email address is not verified.');
});

it('returns preview for eligible group members and excludes online enrollments', function (): void {
    $offline = previewEnroll($this->course, $this->group, 'offline');
    $completed = previewEnroll($this->course, $this->group, 'hybrid', true);
    previewEnroll($this->course, $this->group, 'online');
    previewEnroll($this->course, $this->group, null);

    $otherGroup = LearningGroup::create([
        'group_name' => 'Other',
        'course_id' => $this->course->id,
        'course_instructor_id' => courseInstructorIdFor($this->course, $this->instructor),
        'enrolled_students' => 0,
    ]);
    previewEnroll($this->course, $otherGroup, 'offline');

    $wrongCourseGroup = LearningGroup::create([
        'group_name' => 'Wrong course binding',
        'course_id' => $this->course->id,
        'course_instructor_id' => courseInstructorIdFor($this->course, $this->instructor),
        'enrolled_students' => 0,
    ]);
    $orphanStudent = Student::factory()->create();
    Enrollment::create([
        'student_id' => $orphanStudent->id,
        'course_id' => $this->course->id,
        'group_id' => $wrongCourseGroup->id,
        'attendance_type' => 'offline',
        'price_paid' => 100,
        'is_completed' => false,
    ]);
    Enrollment::create([
        'student_id' => $orphanStudent->id,
        'course_id' => Course::factory()->create(['instructor_id' => $this->instructor->id])->id,
        'group_id' => $this->group->id,
        'attendance_type' => 'offline',
        'price_paid' => 100,
        'is_completed' => false,
    ]);

    $dbBefore = previewRelatedDbSnapshot();

    $response = $this->getJson(previewUrl($this->group))->assertOk();

    expect(previewRelatedDbSnapshot())->toBe($dbBefore);

    $response
        ->assertJsonPath('data.counts.total_enrollments', 4)
        ->assertJsonPath('data.counts.eligible', 3)
        ->assertJsonPath('data.counts.excluded_online', 1)
        ->assertJsonPath('data.future_batch_max', GroupAttendanceCardsPreviewService::FUTURE_BATCH_MAX);

    $ids = collect($response->json('data.students'))->pluck('student_id')->all();

    expect($ids)->toContain($offline->student->id, $completed->student->id)
        ->and($ids)->not->toContain($orphanStudent->id);

    expect(collect($response->json('data.students'))
        ->firstWhere('student_id', $completed->student->id)['enrollment']['is_completed'])->toBeTrue();

    $studentsPayload = $response->json('data.students');
    expect(count($studentsPayload))->toBe($response->json('data.counts.eligible'));
});

it('does not mutate enrollments students or cards on preview', function (): void {
    previewEnroll($this->course, $this->group, 'offline');
    previewEnroll($this->course, $this->group, 'online');

    $dbBefore = previewRelatedDbSnapshot();

    $this->getJson(previewUrl($this->group))->assertOk();

    expect(previewRelatedDbSnapshot())->toBe($dbBefore);
});

it('keeps eligible count aligned with students list under student_course unique constraint', function (): void {
    // Schema: unique(student_id, course_id) — at most one enrollment row per student per course.
    previewEnroll($this->course, $this->group, 'offline');
    previewEnroll($this->course, $this->group, 'hybrid');

    $response = $this->getJson(previewUrl($this->group))->assertOk();

    $eligible = $response->json('data.counts.eligible');
    $students = $response->json('data.students');

    expect(count($students))->toBe($eligible)
        ->and(collect($students)->pluck('student_id')->unique()->count())->toBe(count($students));
});

it('includes inactive student warning and active card metadata without secrets', function (): void {
    $inactive = previewEnroll($this->course, $this->group, 'offline', false, 'inactive');

    $hash = StudentAttendanceCardLifecycleService::hashPlainToken('card_'.str_repeat('a', 64));
    StudentAttendanceCard::factory()->create([
        'student_id' => $inactive->student->id,
        'token_hash' => $hash,
        'token_suffix' => 'aaaa',
        'issued_at' => now(),
    ]);

    $response = $this->getJson(previewUrl($this->group))->assertOk();

    $json = json_encode($response->json());

    expect($json)->not->toContain('plain_text_token')
        ->and($json)->not->toContain('token_hash')
        ->and($json)->not->toMatch('/card_[0-9a-f]{64}/');

    $row = collect($response->json('data.students'))
        ->firstWhere('student_id', $inactive->student->id);

    expect($row['has_active_card'])->toBeTrue()
        ->and($row['student_status'])->toBe('inactive')
        ->and($row['warnings'][0]['code'])->toBe('STUDENT_INACTIVE')
        ->and($row['card']['token_suffix'])->toBe('aaaa');

    $response->assertJsonPath('data.counts.with_active_card', 1)
        ->assertJsonPath('data.counts.inactive_students', 1);
});

it('returns 404 for unknown group on preview', function (): void {
    $this->getJson('/api/admin/learning-groups/999999/attendance-cards/preview')
        ->assertNotFound();
});
