<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GoogleStorageAccount;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\Video\PlaybackTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'student', 'guard_name' => 'web']);
});

function videoLessonFixture(): array
{
    $account = GoogleStorageAccount::factory()->create();
    $course = Course::factory()->create([
        'google_storage_account_id' => $account->id,
        'status' => 'published',
        'published_at' => now(),
    ]);
    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    $user->assignRole('student');
    $student = Student::factory()->create(['user_id' => $user->id]);
    $order = Order::factory()->create([
        'student_id' => $student->id,
        'status' => 'completed',
    ]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'order_id' => $order->id,
    ]);

    return compact('account', 'course', 'lesson', 'user', 'student', 'order');
}

it('returns 401 for unauthenticated playback authorization', function () {
    $fixture = videoLessonFixture();

    $this->postJson("/api/student/lessons/{$fixture['lesson']->id}/playback")
        ->assertUnauthorized();
});

it('returns 403 for non-enrolled student playback authorization', function () {
    $fixture = videoLessonFixture();
    $otherUser = User::factory()->create();
    $otherUser->assignRole('student');
    Student::factory()->create(['user_id' => $otherUser->id]);
    Sanctum::actingAs($otherUser, ['*']);

    $this->postJson("/api/student/lessons/{$fixture['lesson']->id}/playback")
        ->assertForbidden();
});

it('authorizes enrolled student and hides google drive urls', function () {
    $fixture = videoLessonFixture();
    Sanctum::actingAs($fixture['user'], ['*']);

    $response = $this->postJson("/api/student/lessons/{$fixture['lesson']->id}/playback")
        ->assertOk();

    $json = json_encode($response->json());
    expect($json)->not->toContain('drive.google.com');
    expect($json)->not->toContain($fixture['lesson']->google_drive_file_id);
    expect($response->json('data.stream_url'))->toContain('/api/student/lessons/');
    expect($response->json('data.watermark.name'))->not->toBeEmpty();
});

it('rejects invalid playback token on stream endpoint', function () {
    $fixture = videoLessonFixture();
    Sanctum::actingAs($fixture['user'], ['*']);

    $this->getJson("/api/student/lessons/{$fixture['lesson']->id}/stream?token=invalid-token")
        ->assertForbidden();
});

it('rejects playback token for wrong user', function () {
    $fixture = videoLessonFixture();
    $otherUser = User::factory()->create();
    $otherUser->assignRole('student');
    Student::factory()->create(['user_id' => $otherUser->id]);

    $token = app(PlaybackTokenService::class)->issue(
        $otherUser->id,
        $fixture['lesson']->id,
        $fixture['course']->id
    )['token'];

    Sanctum::actingAs($fixture['user'], ['*']);

    $this->get("/api/student/lessons/{$fixture['lesson']->id}/stream?token=".urlencode($token))
        ->assertForbidden();
});
