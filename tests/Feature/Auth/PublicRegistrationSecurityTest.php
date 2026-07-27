<?php

/**
 * Security tests for public student registration.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'student', 'guard_name' => 'web']);
    Role::create(['name' => 'instructor', 'guard_name' => 'web']);
});

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Public Student Name',
        'email' => 'public-student@example.com',
        'password' => 'Password123',
        'phone' => '01098765432',
        'gender' => 'male',
    ], $overrides);
}

it('registers a public user as student only', function (): void {
    $response = $this->postJson('/api/register', validRegistrationPayload());

    $response->assertCreated()
        ->assertJsonPath('status', 'success');

    $user = User::where('email', 'public-student@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe('student');
    expect($user->hasRole('student'))->toBeTrue();
    expect($user->hasRole('instructor'))->toBeFalse();
});

it('rejects privilege escalation via role on public registration', function (): void {
    Log::spy();

    $response = $this->postJson('/api/register', validRegistrationPayload([
        'email' => 'instructor-attempt@example.com',
        'role' => 'instructor',
        'bio' => 'Trying to become instructor through public register endpoint.',
        'field' => 'Security',
    ]));

    $response->assertStatus(422);

    expect(User::where('email', 'instructor-attempt@example.com')->exists())->toBeFalse();

    Log::shouldHaveReceived('warning')
        ->atLeast()
        ->once()
        ->with('suspicious.mass_assignment', \Mockery::on(function (array $context) {
            return ($context['event'] ?? null) === 'forbidden_fields_on_register'
                && in_array('role', $context['forbidden'] ?? [], true);
        }));
});

it('rejects forbidden fields on public registration', function (): void {
    Log::spy();

    $response = $this->postJson('/api/register', validRegistrationPayload([
        'email' => 'forbidden-fields@example.com',
        'group_id' => 1,
        'verified' => now()->toDateTimeString(),
    ]));

    $response->assertStatus(422);

    expect(User::where('email', 'forbidden-fields@example.com')->exists())->toBeFalse();
});

it('rate limits public registration attempts', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/register', validRegistrationPayload([
            'email' => "rate-limit-{$attempt}@example.com",
            'password' => 'short',
        ]))->assertStatus(422);
    }

    $this->postJson('/api/register', validRegistrationPayload([
        'email' => 'rate-limit-final@example.com',
    ]))->assertStatus(429);
});
