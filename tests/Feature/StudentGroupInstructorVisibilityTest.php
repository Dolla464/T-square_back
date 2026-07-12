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

uses(RefreshDatabase::class);

function createEnrolledStudentForCourse(Course $course, ?int $groupId = null, bool $completed = false): array
{
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'status' => 'completed',
    ]);

    $enrollment = Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
        'group_id' => $groupId,
        'price_paid' => 500,
        'is_completed' => $completed,
        'completed_at' => $completed ? now() : null,
    ]);

    return compact('user', 'student', 'order', 'enrollment');
}

function courseWithTwoInstructors(): array
{
    $primary = Instructor::factory()->create(['full_name' => 'Primary Instructor']);
    $secondary = Instructor::factory()->create(['full_name' => 'Secondary Instructor']);

    $course = Course::factory()->create([
        'instructor_id' => $primary->id,
        'status' => 'published',
        'published_at' => now(),
    ]);

    app(CourseInstructorSync::class)->sync($course, [$primary->id, $secondary->id]);
    $course->load('instructors');

    $secondaryPivotId = courseInstructorIdFor($course, $secondary);

    return compact('course', 'primary', 'secondary', 'secondaryPivotId');
}

function baseReviewRatings(): array
{
    return [
        'course_organization' => 5,
        'course_materials' => 5,
        'course_difficulty' => 5,
        'course_assessments' => 5,
        'course_practical_skills' => 5,
        'center_facilities' => 5,
        'center_location' => 5,
        'center_staff' => 5,
        'center_environment' => 5,
        'center_platform' => 5,
    ];
}

function instructorReviewRatings(): array
{
    return [
        'instructor_knowledge' => 5,
        'instructor_clarity' => 5,
        'instructor_responsive' => 5,
        'instructor_engaging' => 5,
        'instructor_fair' => 5,
    ];
}

it('returns all course instructors when enrollment has no group', function (): void {
    ['course' => $course] = courseWithTwoInstructors();

    $resolved = CourseInstructorSync::resolveForEnrollment($course, null);

    expect($resolved)->toHaveCount(2);
});

it('returns only the group instructor when enrollment is assigned to a group', function (): void {
    [
        'course' => $course,
        'secondary' => $secondary,
        'secondaryPivotId' => $secondaryPivotId,
    ] = courseWithTwoInstructors();

    $group = LearningGroup::create([
        'group_name' => 'Filtered Batch',
        'course_id' => $course->id,
        'course_instructor_id' => $secondaryPivotId,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => 'active',
    ]);

    $enrollment = Enrollment::make([
        'student_id' => 1,
        'course_id' => $course->id,
        'group_id' => $group->id,
    ]);
    $enrollment->setRelation('learningGroup', $group);

    $resolved = CourseInstructorSync::resolveForEnrollment($course, $enrollment);

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0]['course_instructor_id'])->toBe($secondaryPivotId)
        ->and($resolved[0]['full_name'])->toBe($secondary->full_name);
});

it('filters instructors on student course details when assigned to a group', function (): void {
    [
        'course' => $course,
        'secondaryPivotId' => $secondaryPivotId,
    ] = courseWithTwoInstructors();

    $group = LearningGroup::create([
        'group_name' => 'Details Batch',
        'course_id' => $course->id,
        'course_instructor_id' => $secondaryPivotId,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => 'active',
    ]);

    ['user' => $user] = createEnrolledStudentForCourse($course, $group->id);
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/student/dashboard/courses/{$course->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data.instructors')
        ->assertJsonPath('data.instructors.0.course_instructor_id', $secondaryPivotId)
        ->assertJsonPath('data.enrollment.group_id', $group->id);
});

it('returns all instructors on student course details when not assigned to a group', function (): void {
    ['course' => $course] = courseWithTwoInstructors();
    ['user' => $user] = createEnrolledStudentForCourse($course);
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/student/dashboard/courses/{$course->id}");

    $response->assertOk()
        ->assertJsonCount(2, 'data.instructors')
        ->assertJsonPath('data.enrollment.group_id', null);
});

it('filters review eligibility instructors to the assigned group instructor', function (): void {
    [
        'course' => $course,
        'secondaryPivotId' => $secondaryPivotId,
    ] = courseWithTwoInstructors();

    $group = LearningGroup::create([
        'group_name' => 'Review Batch',
        'course_id' => $course->id,
        'course_instructor_id' => $secondaryPivotId,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => 'active',
    ]);

    ['user' => $user] = createEnrolledStudentForCourse($course, $group->id, completed: true);
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/student/reviews/eligibility/{$course->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data.instructors')
        ->assertJsonPath('data.instructors.0.course_instructor_id', $secondaryPivotId);
});

it('rejects review ratings for instructors outside the student group assignment', function (): void {
    [
        'course' => $course,
        'primary' => $primary,
        'secondaryPivotId' => $secondaryPivotId,
    ] = courseWithTwoInstructors();

    $primaryPivotId = courseInstructorIdFor($course, $primary);

    $group = LearningGroup::create([
        'group_name' => 'Reject Batch',
        'course_id' => $course->id,
        'course_instructor_id' => $secondaryPivotId,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => 'active',
    ]);

    ['user' => $user] = createEnrolledStudentForCourse($course, $group->id, completed: true);
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/student/reviews', [
        'course_id' => $course->id,
        'overall_comment' => 'Great course experience overall.',
        'ratings' => baseReviewRatings(),
        'instructor_ratings' => [
            [
                'course_instructor_id' => $primaryPivotId,
                'ratings' => instructorReviewRatings(),
            ],
        ],
    ]);

    $response->assertStatus(422);
});

it('accepts review ratings for the assigned group instructor', function (): void {
    [
        'course' => $course,
        'secondaryPivotId' => $secondaryPivotId,
    ] = courseWithTwoInstructors();

    $group = LearningGroup::create([
        'group_name' => 'Accept Batch',
        'course_id' => $course->id,
        'course_instructor_id' => $secondaryPivotId,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(4)->toDateString(),
        'status' => 'active',
    ]);

    ['user' => $user] = createEnrolledStudentForCourse($course, $group->id, completed: true);
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/student/reviews', [
        'course_id' => $course->id,
        'overall_comment' => 'Great course experience overall.',
        'ratings' => baseReviewRatings(),
        'instructor_ratings' => [
            [
                'course_instructor_id' => $secondaryPivotId,
                'ratings' => instructorReviewRatings(),
            ],
        ],
    ]);

    $response->assertCreated();
});
