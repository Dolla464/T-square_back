<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Support\CourseInstructorSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);
});

function adminStudentCourseWithTwoInstructors(): array
{
    $primary = Instructor::factory()->create(['full_name' => 'Primary Instructor']);
    $secondary = Instructor::factory()->create(['full_name' => 'Secondary Instructor']);

    $course = Course::factory()->create([
        'instructor_id' => $primary->id,
        'status' => 'published',
        'published_at' => now(),
    ]);

    app(CourseInstructorSync::class)->sync($course, [$primary->id, $secondary->id]);

    return [
        'course' => $course,
        'primary' => $primary,
        'secondary' => $secondary,
        'secondaryPivotId' => courseInstructorIdFor($course, $secondary),
    ];
}

function createAdminStudentEnrollment(Course $course, ?int $groupId = null): Student
{
    $student = Student::factory()->create(['user_id' => User::factory()->create()->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'status' => 'completed',
    ]);

    Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $groupId,
        'price_paid' => 500,
        'is_completed' => false,
    ]);

    return $student;
}

it('returns the group instructor name in enrolled courses when student has a group', function (): void {
    [
        'course' => $course,
        'secondary' => $secondary,
        'secondaryPivotId' => $secondaryPivotId,
    ] = adminStudentCourseWithTwoInstructors();

    $group = LearningGroup::create([
        'group_name' => 'Admin View Batch',
        'course_id' => $course->id,
        'course_instructor_id' => $secondaryPivotId,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => 'active',
    ]);

    $student = createAdminStudentEnrollment($course, $group->id);

    $response = $this->getJson("/api/admin/students/{$student->id}");

    $response->assertOk()
        ->assertJsonPath('data.enrolled_courses.0.instructor_name', $secondary->full_name);
});

it('returns the primary course instructor in enrolled courses when student has no group', function (): void {
    [
        'course' => $course,
        'primary' => $primary,
    ] = adminStudentCourseWithTwoInstructors();

    $student = createAdminStudentEnrollment($course);

    $response = $this->getJson("/api/admin/students/{$student->id}");

    $response->assertOk()
        ->assertJsonPath('data.enrolled_courses.0.instructor_name', $primary->full_name);
});
