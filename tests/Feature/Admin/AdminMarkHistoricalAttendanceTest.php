<?php

/**
 * Feature tests for admin historical attendance marking.
 *
 * POST /api/admin/learning-groups/{learningGroup}/sessions/{session}/attendance/mark
 */

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\LearningGroupSchedule;
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

    $adminRole   = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole($adminRole);
    Sanctum::actingAs($this->admin, ['*']);

    $this->instructor = Instructor::factory()->create();

    $this->course = Course::factory()->create([
        'instructor_id'  => $this->instructor->id,
        'duration_weeks' => 4,
    ]);
});

function createGroupWithSessionAndStudent(array $sessionOverrides = []): array
{
    $group = LearningGroup::factory()->create([
        'course_id' => test()->course->id,
        'course_instructor_id' => courseInstructorIdFor(test()->course, test()->instructor),
        'start_date' => now()->subWeeks(4)->toDateString(),
        'end_date'      => now()->addWeek()->toDateString(),
        'status'        => 'active',
    ]);

    $schedule = LearningGroupSchedule::create([
        'learning_group_id' => $group->id,
        'day_of_week'       => 2,
        'start_time'        => '10:00',
        'end_time'          => '12:00',
        'room'              => 'A1',
    ]);

    $session = AttendanceSession::create(array_merge([
        'learning_group_id' => $group->id,
        'schedule_id'       => $schedule->id,
        'session_date'      => now()->subWeek()->toDateString(),
        'status'            => 'completed',
    ], $sessionOverrides));

    $student = Student::factory()->create();

    $order = Order::create([
        'student_id'    => $student->id,
        'total_amount'  => 500,
        'status'        => 'completed',
        'billing_name'  => 'Test Billing',
        'billing_email' => 'billing@test.com',
        'billing_phone' => '01000000000',
    ]);

    Enrollment::create([
        'student_id'   => $student->id,
        'course_id'    => test()->course->id,
        'order_id'     => $order->id,
        'group_id'     => $group->id,
        'price_paid'   => 500,
        'is_completed' => false,
    ]);

    return compact('group', 'schedule', 'session', 'student');
}

function markAttendanceUrl(LearningGroup $group, AttendanceSession $session): string
{
    return "/api/admin/learning-groups/{$group->id}/sessions/{$session->id}/attendance/mark";
}

test('admin can mark attendance on a completed session', function (): void {
    ['group' => $group, 'session' => $session, 'student' => $student] = createGroupWithSessionAndStudent();

    $response = $this->postJson(markAttendanceUrl($group, $session), [
        'student_id' => $student->id,
        'status'     => 'present',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'present')
        ->assertJsonPath('data.marked_by', 'admin_manual');

    $this->assertDatabaseHas('attendance_records', [
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status'     => 'present',
        'marked_by'  => 'admin_manual',
    ]);
});

test('admin can update an existing attendance record', function (): void {
    ['group' => $group, 'session' => $session, 'student' => $student] = createGroupWithSessionAndStudent();

    AttendanceRecord::create([
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status'     => 'absent',
        'marked_by'  => 'system',
        'marked_at'  => now()->subDay(),
    ]);

    $response = $this->postJson(markAttendanceUrl($group, $session), [
        'student_id' => $student->id,
        'status'     => 'present',
        'notes'      => 'Corrected by admin',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'present')
        ->assertJsonPath('data.marked_by', 'admin_manual');

    $this->assertDatabaseHas('attendance_records', [
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status'     => 'present',
        'marked_by'  => 'admin_manual',
        'notes'      => 'Corrected by admin',
    ]);

    expect(AttendanceRecord::where('session_id', $session->id)->count())->toBe(1);
});

test('admin cannot mark attendance on cancelled sessions', function (): void {
    ['group' => $group, 'session' => $session, 'student' => $student] = createGroupWithSessionAndStudent([
        'status' => 'cancelled',
    ]);

    $response = $this->postJson(markAttendanceUrl($group, $session), [
        'student_id' => $student->id,
        'status'     => 'present',
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('attendance_records', [
        'session_id' => $session->id,
        'student_id' => $student->id,
    ]);
});

test('admin cannot mark attendance on future upcoming sessions', function (): void {
    ['group' => $group, 'session' => $session, 'student' => $student] = createGroupWithSessionAndStudent([
        'session_date' => now()->addWeek()->toDateString(),
        'status'       => 'upcoming',
    ]);

    $response = $this->postJson(markAttendanceUrl($group, $session), [
        'student_id' => $student->id,
        'status'     => 'present',
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('attendance_records', [
        'session_id' => $session->id,
        'student_id' => $student->id,
    ]);
});

test('admin cannot mark attendance when session does not belong to group', function (): void {
    ['group' => $group, 'student' => $student] = createGroupWithSessionAndStudent();

    $otherGroup = LearningGroup::factory()->create([
        'course_id' => $this->course->id,
        'course_instructor_id' => courseInstructorIdFor($this->course, $this->instructor),
        'start_date' => now()->subWeeks(4)->toDateString(),
        'end_date'      => now()->addWeek()->toDateString(),
    ]);

    $otherSchedule = LearningGroupSchedule::create([
        'learning_group_id' => $otherGroup->id,
        'day_of_week'       => 2,
        'start_time'        => '10:00',
        'end_time'          => '12:00',
    ]);

    $otherSession = AttendanceSession::create([
        'learning_group_id' => $otherGroup->id,
        'schedule_id'       => $otherSchedule->id,
        'session_date'      => now()->subWeek()->toDateString(),
        'status'            => 'completed',
    ]);

    $response = $this->postJson(markAttendanceUrl($group, $otherSession), [
        'student_id' => $student->id,
        'status'     => 'present',
    ]);

    $response->assertNotFound();
});

test('admin cannot mark attendance for student not enrolled in group', function (): void {
    ['group' => $group, 'session' => $session] = createGroupWithSessionAndStudent();

    $outsider = Student::factory()->create();

    $response = $this->postJson(markAttendanceUrl($group, $session), [
        'student_id' => $outsider->id,
        'status'     => 'present',
    ]);

    $response->assertStatus(422);
});

test('admin can mark attendance on past session even when status is upcoming', function (): void {
    ['group' => $group, 'session' => $session, 'student' => $student] = createGroupWithSessionAndStudent([
        'session_date' => now()->subDays(3)->toDateString(),
        'status'       => 'upcoming',
    ]);

    $response = $this->postJson(markAttendanceUrl($group, $session), [
        'student_id' => $student->id,
        'status'     => 'late',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'late');

    $this->assertDatabaseHas('attendance_records', [
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status'     => 'late',
    ]);
});
