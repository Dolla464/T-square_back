<?php

use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedNotificationsForUser(User $user, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\StudentEnrolledNotification',
            'data' => [
                'type' => 'enrollment',
                'title' => "Notification {$i}",
                'message' => 'Test message',
            ],
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ]);
    }
}

it('paginates notifications with 30 per page', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);
    seedNotificationsForUser($user, 31);

    $page1 = $this->getJson('/api/notifications?page=1&per_page=30');

    $page1->assertOk()
        ->assertJsonPath('meta.total', 31)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 30)
        ->assertJsonPath('meta.current_page', 1);

    expect($page1->json('data'))->toHaveCount(30);

    $page1Ids = collect($page1->json('data'))->pluck('id');

    $page2 = $this->getJson('/api/notifications?page=2&per_page=30');

    $page2->assertOk()
        ->assertJsonPath('meta.current_page', 2);

    expect($page2->json('data'))->toHaveCount(1);

    $page2Ids = collect($page2->json('data'))->pluck('id');

    expect($page1Ids->intersect($page2Ids)->isEmpty())->toBeTrue();
});

it('defaults per_page to 30 when omitted', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);
    seedNotificationsForUser($user, 31);

    $response = $this->getJson('/api/notifications?page=1');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', NotificationService::DEFAULT_PER_PAGE);

    expect($response->json('data'))->toHaveCount(30);
});

it('orders notifications by created_at desc from the database', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $older = $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\StudentEnrolledNotification',
        'data' => ['type' => 'enrollment', 'title' => 'Older', 'message' => 'Old'],
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $newer = $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\StudentEnrolledNotification',
        'data' => ['type' => 'enrollment', 'title' => 'Newer', 'message' => 'New'],
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    $response = $this->getJson('/api/notifications?page=1&per_page=30');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids[0])->toBe($newer->id)
        ->and($ids[1])->toBe($older->id);
});

it('returns unread_count as a scalar in meta', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    for ($i = 0; $i < 2; $i++) {
        $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\StudentEnrolledNotification',
            'data' => ['type' => 'enrollment', 'title' => "Unread {$i}", 'message' => 'Test'],
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ]);
    }

    $user->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => 'App\Notifications\StudentEnrolledNotification',
        'data' => ['type' => 'enrollment', 'title' => 'Read', 'message' => 'Test'],
        'read_at' => now(),
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    $response = $this->getJson('/api/notifications?page=1&per_page=30');

    $response->assertOk()
        ->assertJsonPath('meta.total', 3);

    $unread = $response->json('meta.unread_count');

    expect($unread)->toBe(2)->toBeInt();
});

it('clamps per_page between 1 and 100', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);
    seedNotificationsForUser($user, 3);

    $tooLow = $this->getJson('/api/notifications?page=1&per_page=0');
    $tooLow->assertOk()->assertJsonPath('meta.per_page', 1);

    $tooHigh = $this->getJson('/api/notifications?page=1&per_page=500');
    $tooHigh->assertOk()->assertJsonPath('meta.per_page', NotificationService::MAX_PER_PAGE);
});
