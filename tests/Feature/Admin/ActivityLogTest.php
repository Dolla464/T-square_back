<?php

use App\Http\Middleware\EnsureActivityLogVerified;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLog\RequestDataSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['admin', 'student', 'instructor', 'receptionist'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create([
        'name' => 'Admin User',
        'password' => Hash::make('AdminPass123'),
    ]);
    $this->admin->assignRole('admin');

    $this->student = User::factory()->create([
        'name' => 'Student User',
        'password' => Hash::make('StudentPass123'),
    ]);
    $this->student->assignRole('student');
});

function issueActivityLogToken(User $admin): string
{
    Sanctum::actingAs($admin, ['*']);

    $response = test()->postJson('/api/admin/activity-logs/verify-password', [
        'password' => 'AdminPass123',
    ])->assertOk();

    return $response->json('data.token');
}

it('logs authenticated admin GET requests via middleware', function (): void {
    Sanctum::actingAs($this->admin, ['*']);

    expect(ActivityLog::count())->toBe(0);

    $this->getJson('/api/admin/dashboard/stats')
        ->assertOk();

    $log = ActivityLog::first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->user_name)->toBe('Admin User')
        ->and($log->role)->toBe('admin')
        ->and($log->http_method)->toBe('GET');
});

it('logs guest login POST with role guest and null user', function (): void {
    $this->postJson('/api/login', [
        'email' => $this->admin->email,
        'password' => 'AdminPass123',
    ])->assertOk();

    $log = ActivityLog::where('path', '/api/login')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->user_name)->toBeNull()
        ->and($log->role)->toBe('guest');
});

it('logs guest register POST with role guest', function (): void {
    $this->postJson('/api/register', [
        'full_name' => 'New Guest Student',
        'email' => 'newguest@example.com',
        'phone' => '01012345678',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertCreated();

    $log = ActivityLog::where('path', '/api/register')->first();

    expect($log)->not->toBeNull()
        ->and($log->role)->toBe('guest')
        ->and($log->user_id)->toBeNull();
});

it('verifies activity log password and rejects wrong password', function (): void {
    Sanctum::actingAs($this->admin, ['*']);

    $this->postJson('/api/admin/activity-logs/verify-password', [
        'password' => 'wrong-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    $this->postJson('/api/admin/activity-logs/verify-password', [
        'password' => 'AdminPass123',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['token', 'expires_at']]);
});

it('returns paginated activity logs when token is valid', function (): void {
    ActivityLog::create([
        'user_id' => $this->admin->id,
        'user_name' => 'Admin User',
        'role' => 'admin',
        'http_method' => 'GET',
        'path' => '/api/admin/students',
        'description' => 'admin students index',
        'response_status' => 200,
        'created_at' => now(),
    ]);

    $token = issueActivityLogToken($this->admin);

    $this->getJson('/api/admin/activity-logs', [
        'X-Activity-Log-Token' => $token,
    ])->assertOk()
        ->assertJsonPath('data.0.user_name', 'Admin User')
        ->assertJsonStructure(['pagination' => ['total', 'current_page']]);
});

it('blocks activity log index without verification token', function (): void {
    Sanctum::actingAs($this->admin, ['*']);

    $this->getJson('/api/admin/activity-logs')
        ->assertForbidden();
});

it('does not break original API response when activity logging runs', function (): void {
    Sanctum::actingAs($this->admin, ['*']);

    $response = $this->getJson('/api/admin/dashboard/stats');

    $response->assertOk()
        ->assertJsonPath('status', 'success');

    expect(ActivityLog::count())->toBeGreaterThan(0);
});

it('does not break original API response when activity log dispatch fails', function (): void {
    Sanctum::actingAs($this->admin, ['*']);

    Bus::fake();

    Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('Queue unavailable'));

    $this->getJson('/api/admin/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

it('sanitizes nested sensitive fields in request data', function (): void {
    $sanitizer = app(RequestDataSanitizer::class);

    $result = $sanitizer->sanitize([
        'user' => [
            'name' => 'Adel',
            'password' => 'secret',
            'credentials' => [
                'access_token' => 'abc',
            ],
        ],
        'client_secret' => 'hidden',
    ]);

    expect($result['user']['name'])->toBe('Adel')
        ->and($result['user']['password'])->toBe('[REDACTED]')
        ->and($result['user']['credentials']['access_token'])->toBe('[REDACTED]')
        ->and($result['client_secret'])->toBe('[REDACTED]');
});

it('stores uploaded files as placeholders in request data', function (): void {
    Sanctum::actingAs($this->admin, ['*']);

    $this->postJson('/api/admin/questions/upload-image', [
        'image' => UploadedFile::fake()->image('question.jpg'),
    ]);

    $log = ActivityLog::where('path', '/api/admin/questions/upload-image')->first();

    expect($log)->not->toBeNull();

    $encoded = json_encode($log->request_data);

    expect($encoded)->toContain('[uploaded file]')
        ->and($encoded)->not->toContain('fake');
});

it('prevents non-admin from verifying activity log password', function (): void {
    Sanctum::actingAs($this->student, ['*']);

    $this->postJson('/api/admin/activity-logs/verify-password', [
        'password' => 'StudentPass123',
    ])->assertForbidden();
});

it('prevents non-admin from accessing activity logs even with valid admin token', function (): void {
    $token = issueActivityLogToken($this->admin);

    Sanctum::actingAs($this->student, ['*']);

    $this->getJson('/api/admin/activity-logs', [
        'X-Activity-Log-Token' => $token,
    ])->assertForbidden();
});

it('stores hashed token in cache not raw token', function (): void {
    Sanctum::actingAs($this->admin, ['*']);

    $response = $this->postJson('/api/admin/activity-logs/verify-password', [
        'password' => 'AdminPass123',
    ])->assertOk();

    $token = $response->json('data.token');

    expect(Cache::has(EnsureActivityLogVerified::cacheKey($this->admin->id, $token)))->toBeTrue()
        ->and(Cache::has('activity_log_verified:'.$this->admin->id.':'.$token))->toBeFalse();
});

it('preserves user_name snapshot when user name changes later', function (): void {
    Sanctum::actingAs($this->admin, ['*']);

    $this->getJson('/api/admin/dashboard/stats')->assertOk();

    $log = ActivityLog::first();
    expect($log->user_name)->toBe('Admin User');

    $this->admin->update(['name' => 'Renamed Admin']);

    $log->refresh();
    expect($log->user_name)->toBe('Admin User');
});
