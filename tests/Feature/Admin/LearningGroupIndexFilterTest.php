<?php

use App\Models\Course;
use App\Models\Instructor;
use App\Models\LearningGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    $this->instructorA = Instructor::factory()->create(['full_name' => 'Instructor Alpha']);
    $this->instructorB = Instructor::factory()->create(['full_name' => 'Instructor Beta']);

    $this->courseA = Course::factory()->create([
        'title' => 'Alpha Programming Course',
        'instructor_id' => $this->instructorA->id,
    ]);
    $this->courseB = Course::factory()->create([
        'title' => 'Beta Design Course',
        'instructor_id' => $this->instructorB->id,
    ]);

    $this->courseInstructorA = courseInstructorIdFor($this->courseA, $this->instructorA);
    $this->courseInstructorB = courseInstructorIdFor($this->courseB, $this->instructorB);
});

function createListedGroup(
    Course $course,
    int $courseInstructorId,
    string $status,
    string $name,
    ?Carbon $createdAt = null,
): LearningGroup {
    $group = LearningGroup::create([
        'group_name' => $name,
        'course_id' => $course->id,
        'course_instructor_id' => $courseInstructorId,
        'start_date' => '2026-01-01',
        'end_date' => '2026-06-01',
        'status' => $status,
    ]);

    if ($createdAt !== null) {
        $group->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
    }

    return $group->fresh();
}

it('filters learning groups by completed status', function (): void {
    $completedA = createListedGroup($this->courseA, $this->courseInstructorA, 'completed', 'Completed A');
    $completedB = createListedGroup($this->courseA, $this->courseInstructorA, 'completed', 'Completed B');
    createListedGroup($this->courseA, $this->courseInstructorA, 'active', 'Active Batch');

    $response = $this->getJson('/api/admin/learning-groups?status=completed');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($completedA->id, $completedB->id)
        ->and($ids)->not->toContain(
            LearningGroup::query()->where('group_name', 'Active Batch')->value('id')
        );
});

it('returns only active groups when status filter is active', function (): void {
    createListedGroup($this->courseA, $this->courseInstructorA, 'completed', 'Completed Batch');
    $active = createListedGroup($this->courseA, $this->courseInstructorA, 'active', 'Active Batch');

    $response = $this->getJson('/api/admin/learning-groups?status=active');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain(
            LearningGroup::query()->where('group_name', 'Completed Batch')->value('id')
        );
});

it('filters learning groups by cancelled status', function (): void {
    $cancelled = createListedGroup($this->courseA, $this->courseInstructorA, 'cancelled', 'Cancelled Batch');
    createListedGroup($this->courseA, $this->courseInstructorA, 'active', 'Active Batch');

    $response = $this->getJson('/api/admin/learning-groups?status=cancelled');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($cancelled->id)
        ->and($ids)->not->toContain(
            LearningGroup::query()->where('group_name', 'Active Batch')->value('id')
        );
});

it('searches by group name and course title', function (): void {
    $byGroupName = createListedGroup($this->courseA, $this->courseInstructorA, 'active', 'Unique Group Label');
    $byCourseTitle = createListedGroup($this->courseB, $this->courseInstructorB, 'active', 'Other Batch');

    $groupNameResponse = $this->getJson('/api/admin/learning-groups?search=Unique+Group');
    $groupNameResponse->assertOk();
    expect(collect($groupNameResponse->json('data'))->pluck('id')->all())->toContain($byGroupName->id);

    $courseTitleResponse = $this->getJson('/api/admin/learning-groups?search=Beta+Design');
    $courseTitleResponse->assertOk();
    expect(collect($courseTitleResponse->json('data'))->pluck('id')->all())->toContain($byCourseTitle->id);
});

it('filters learning groups by instructor id', function (): void {
    $instructorAGroup = createListedGroup($this->courseA, $this->courseInstructorA, 'active', 'Alpha Group');
    createListedGroup($this->courseB, $this->courseInstructorB, 'active', 'Beta Group');

    $response = $this->getJson('/api/admin/learning-groups?instructor_id='.$this->instructorA->id);

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($instructorAGroup->id)
        ->and($ids)->not->toContain(
            LearningGroup::query()->where('group_name', 'Beta Group')->value('id')
        );
});

it('filters learning groups by course id', function (): void {
    $courseAGroup = createListedGroup($this->courseA, $this->courseInstructorA, 'active', 'Alpha Group');
    createListedGroup($this->courseB, $this->courseInstructorB, 'active', 'Beta Group');

    $response = $this->getJson('/api/admin/learning-groups?course_id='.$this->courseA->id);

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($courseAGroup->id)
        ->and($ids)->not->toContain(
            LearningGroup::query()->where('group_name', 'Beta Group')->value('id')
        );
});

it('filters learning groups created in the last week', function (): void {
    $recent = createListedGroup(
        $this->courseA,
        $this->courseInstructorA,
        'active',
        'Recent Group',
        Carbon::now()->subDays(2),
    );
    createListedGroup(
        $this->courseA,
        $this->courseInstructorA,
        'active',
        'Old Group',
        Carbon::now()->subDays(20),
    );

    $response = $this->getJson('/api/admin/learning-groups?time=last_week');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($recent->id)
        ->and($ids)->not->toContain(
            LearningGroup::query()->where('group_name', 'Old Group')->value('id')
        );
});

it('combines status and instructor filters', function (): void {
    $match = createListedGroup($this->courseA, $this->courseInstructorA, 'completed', 'Match Group');
    createListedGroup($this->courseA, $this->courseInstructorA, 'active', 'Active Same Instructor');
    createListedGroup($this->courseB, $this->courseInstructorB, 'completed', 'Completed Other Instructor');

    $response = $this->getJson('/api/admin/learning-groups?status=completed&instructor_id='.$this->instructorA->id);

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($match->id)
        ->and($ids)->not->toContain(
            LearningGroup::query()->where('group_name', 'Active Same Instructor')->value('id'),
            LearningGroup::query()->where('group_name', 'Completed Other Instructor')->value('id'),
        );
});

it('paginates filtered learning groups using page query parameter', function (): void {
    foreach (range(1, 11) as $index) {
        createListedGroup(
            $this->courseA,
            $this->courseInstructorA,
            'completed',
            'Completed Page Group '.$index,
        );
    }

    $pageOne = $this->getJson('/api/admin/learning-groups?status=completed&per_page=10&page=1');
    $pageTwo = $this->getJson('/api/admin/learning-groups?status=completed&per_page=10&page=2');

    $pageOne->assertOk();
    $pageTwo->assertOk();

    expect($pageOne->json('data'))->toHaveCount(10)
        ->and($pageTwo->json('data'))->toHaveCount(1)
        ->and($pageOne->json('pagination.current_page'))->toBe(1)
        ->and($pageTwo->json('pagination.current_page'))->toBe(2);
});

it('ignores invalid status filter values', function (): void {
    createListedGroup($this->courseA, $this->courseInstructorA, 'active', 'Active Batch');
    createListedGroup($this->courseA, $this->courseInstructorA, 'completed', 'Completed Batch');

    $response = $this->getJson('/api/admin/learning-groups?status=invalid-status');

    $response->assertOk();

    expect(collect($response->json('data')))->toHaveCount(2);
});
