<?php

use App\Models\Category;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function adminCoursePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Test Course',
        'status' => 'draft',
        'category_id' => test()->category->id,
        'instructor_ids' => [test()->instructor->id],
        'attendance_types' => ['online'],
    ], $overrides);
}

beforeEach(function (): void {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);

    $this->category = Category::factory()->create(['status' => 'active']);
    $this->instructor = Instructor::factory()->create();
});

it('creates a course with a single attendance type', function (): void {
    $response = $this->postJson('/api/admin/courses', adminCoursePayload([
        'attendance_types' => ['online'],
    ]))->assertCreated();

    $courseId = $response->json('data.id');

    expect(Course::find($courseId)->attendance_types)->toBe(['online'])
        ->and($response->json('data.attendance_types'))->toBe(['online'])
        ->and($response->json('data'))->not->toHaveKey('attendance_type');
});

it('creates a course with multiple attendance types', function (): void {
    $response = $this->postJson('/api/admin/courses', adminCoursePayload([
        'attendance_types' => ['online', 'offline'],
    ]))->assertCreated();

    $courseId = $response->json('data.id');

    expect(Course::find($courseId)->attendance_types)->toBe(['online', 'offline'])
        ->and($response->json('data.attendance_types'))->toBe(['online', 'offline']);
});

it('creates a course with all supported attendance types', function (): void {
    $response = $this->postJson('/api/admin/courses', adminCoursePayload([
        'attendance_types' => ['online', 'offline', 'hybrid'],
    ]))->assertCreated();

    expect(Course::find($response->json('data.id'))->attendance_types)
        ->toBe(['online', 'offline', 'hybrid']);
});

it('rejects course creation when attendance_types is missing', function (): void {
    $payload = adminCoursePayload();
    unset($payload['attendance_types']);

    $this->postJson('/api/admin/courses', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_types']);
});

it('rejects course creation when attendance_types is empty', function (): void {
    $this->postJson('/api/admin/courses', adminCoursePayload([
        'attendance_types' => [],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_types']);
});

it('rejects course creation when attendance_types contains an invalid value', function (): void {
    $this->postJson('/api/admin/courses', adminCoursePayload([
        'attendance_types' => ['invalid'],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_types.0']);
});

it('rejects course creation when attendance_types contains duplicate values', function (): void {
    $this->postJson('/api/admin/courses', adminCoursePayload([
        'attendance_types' => ['online', 'online'],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_types.1']);
});

it('rejects course creation when attendance_types contains mixed valid and invalid values', function (): void {
    $this->postJson('/api/admin/courses', adminCoursePayload([
        'attendance_types' => ['online', 'invalid'],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance_types.1']);
});

it('replaces attendance_types on course update', function (): void {
    $course = Course::factory()->create([
        'attendance_types' => ['online'],
        'category_id' => $this->category->id,
        'instructor_id' => $this->instructor->id,
    ]);

    $this->putJson("/api/admin/courses/{$course->id}", [
        'attendance_types' => ['offline', 'hybrid'],
    ])->assertOk()
        ->assertJsonPath('data.attendance_types', ['offline', 'hybrid']);

    expect($course->fresh()->attendance_types)->toBe(['offline', 'hybrid']);
});

it('exposes attendance_types on admin course list', function (): void {
    $course = Course::factory()->create([
        'attendance_types' => ['online', 'offline'],
        'category_id' => $this->category->id,
        'instructor_id' => $this->instructor->id,
        'status' => 'published',
    ]);

    $response = $this->getJson('/api/admin/courses?status=published')->assertOk();
    $listedCourse = collect($response->json('data'))
        ->firstWhere('id', $course->id);

    expect($listedCourse)->not->toBeNull()
        ->and($listedCourse['attendance_types'])->toBe(['online', 'offline'])
        ->and($listedCourse)->not->toHaveKey('attendance_type');
});

it('exposes attendance_types on admin course show and omits attendance_type', function (): void {
    $course = Course::factory()->create([
        'attendance_types' => ['online', 'offline'],
        'category_id' => $this->category->id,
        'instructor_id' => $this->instructor->id,
    ]);

    $response = $this->getJson("/api/admin/courses/{$course->id}")->assertOk();

    expect($response->json('data.attendance_types'))->toBe(['online', 'offline'])
        ->and($response->json('data'))->not->toHaveKey('attendance_type');
});

it('exposes attendance_types on public course list and details responses', function (): void {
    $course = Course::factory()->create([
        'attendance_types' => ['online', 'offline'],
        'category_id' => $this->category->id,
        'instructor_id' => $this->instructor->id,
        'status' => 'published',
        'published_at' => now(),
    ]);

    $listResponse = $this->getJson('/api/student/courses')->assertOk();
    $listedCourse = collect($listResponse->json('data'))
        ->firstWhere('slug', $course->slug);

    expect($listedCourse)->not->toBeNull()
        ->and($listedCourse['attendance_types'])->toBe(['online', 'offline'])
        ->and($listedCourse)->not->toHaveKey('attendance_type');

    $detailsResponse = $this->getJson("/api/student/courses/{$course->slug}")->assertOk();

    expect($detailsResponse->json('data.course.attendance_types'))->toBe(['online', 'offline'])
        ->and($detailsResponse->json('data.course'))->not->toHaveKey('attendance_type');
});
