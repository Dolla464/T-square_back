<?php

use App\Models\Course;
use App\Models\Exam;
use App\Models\Instructor;
use App\Support\CourseInstructorSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'instructor', 'guard_name' => 'web']);
});

function courseWithTwoInstructorsForExams(): array
{
    $primary = Instructor::factory()->create(['full_name' => 'Primary Instructor']);
    $secondary = Instructor::factory()->create(['full_name' => 'Secondary Instructor']);

    $course = Course::factory()->create([
        'instructor_id' => $primary->id,
        'status' => 'published',
        'published_at' => now(),
    ]);

    app(CourseInstructorSync::class)->sync($course, [$primary->id, $secondary->id]);

    return compact('course', 'primary', 'secondary');
}

function actingAsInstructor(Instructor $instructor): void
{
    $user = $instructor->user;
    $user->assignRole('instructor');
    Sanctum::actingAs($user, ['*']);
}

function validExamPayload(int $courseId, array $overrides = []): array
{
    return array_merge([
        'course_id' => $courseId,
        'title' => 'Secondary Instructor Exam',
        'description' => 'Exam created by secondary instructor',
        'duration' => 60,
        'total_marks' => 100,
        'passing_mark' => 60,
        'is_active' => true,
        'is_final' => false,
        'max_attempts' => 2,
        'questions_per_attempt' => 10,
        'shuffle_questions' => true,
    ], $overrides);
}

it('lets a secondary instructor list course exams', function (): void {
    ['course' => $course, 'primary' => $primary, 'secondary' => $secondary] = courseWithTwoInstructorsForExams();

    $exam = Exam::factory()->create([
        'course_id' => $course->id,
        'title' => 'Shared Course Exam',
    ]);

    actingAsInstructor($secondary);

    $response = $this->getJson('/api/instructor/exams');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $exam->id)
        ->assertJsonPath('data.0.title', 'Shared Course Exam');

    actingAsInstructor($primary);

    $this->getJson('/api/instructor/exams')
        ->assertOk()
        ->assertJsonPath('data.0.id', $exam->id);
});

it('lets a secondary instructor create an exam for an assigned course', function (): void {
    ['course' => $course, 'secondary' => $secondary] = courseWithTwoInstructorsForExams();

    actingAsInstructor($secondary);

    $response = $this->postJson('/api/instructor/exams', validExamPayload($course->id));

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Secondary Instructor Exam')
        ->assertJsonPath('data.course.id', $course->id);

    $this->assertDatabaseHas('exams', [
        'course_id' => $course->id,
        'title' => 'Secondary Instructor Exam',
    ]);
});

it('lets a secondary instructor toggle status, soft delete, and restore exams', function (): void {
    ['course' => $course, 'secondary' => $secondary] = courseWithTwoInstructorsForExams();

    $exam = Exam::factory()->create([
        'course_id' => $course->id,
        'is_active' => true,
    ]);

    actingAsInstructor($secondary);

    $this->patchJson("/api/instructor/exams/{$exam->id}/toggle-status", [
        'is_active' => 0,
    ])->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson("/api/instructor/exams/{$exam->id}")
        ->assertOk();

    $this->assertSoftDeleted('exams', ['id' => $exam->id]);

    $this->postJson("/api/instructor/exams/{$exam->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.id', $exam->id);

    $this->assertDatabaseHas('exams', [
        'id' => $exam->id,
        'deleted_at' => null,
    ]);
});

it('rejects exam creation when the instructor is not assigned to the course', function (): void {
    ['course' => $course] = courseWithTwoInstructorsForExams();
    $outsider = Instructor::factory()->create();

    actingAsInstructor($outsider);

    $this->postJson('/api/instructor/exams', validExamPayload($course->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['course_id']);
});

it('denies exam deletion when the instructor is not assigned to the course', function (): void {
    ['course' => $course] = courseWithTwoInstructorsForExams();
    $outsider = Instructor::factory()->create();

    $exam = Exam::factory()->create(['course_id' => $course->id]);

    actingAsInstructor($outsider);

    $this->deleteJson("/api/instructor/exams/{$exam->id}")
        ->assertForbidden();
});
