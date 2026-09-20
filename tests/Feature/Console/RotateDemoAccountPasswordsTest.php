<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

const DEMO_ENV_KEYS = [
    'SEED_ADMIN_PASSWORD',
    'SEED_INSTRUCTOR_PASSWORD',
    'SEED_STUDENT_PASSWORD',
    'SEED_RECEPTIONIST_PASSWORD',
];

const DEMO_EMAILS = [
    'admin@tsquare.com',
    'instructor@tsquare.com',
    'student@tsquare.com',
    'receptionist@tsquare.com',
];

function setDemoPasswordEnv(array $overrides = []): array
{
    $passwords = array_merge([
        'SEED_ADMIN_PASSWORD' => 'AdminSecurePass1',
        'SEED_INSTRUCTOR_PASSWORD' => 'InstructorSecurePass1',
        'SEED_STUDENT_PASSWORD' => 'StudentSecurePass1',
        'SEED_RECEPTIONIST_PASSWORD' => 'ReceptionistSecurePass1',
    ], $overrides);

    foreach ($passwords as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    return $passwords;
}

function clearDemoPasswordEnv(): void
{
    foreach (DEMO_ENV_KEYS as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}

function createDemoUsers(): void
{
    foreach (DEMO_EMAILS as $email) {
        User::factory()->create([
            'email' => $email,
            'password' => 'OldPassword123',
        ]);
    }
}

beforeEach(function (): void {
    clearDemoPasswordEnv();
});

afterEach(function (): void {
    clearDemoPasswordEnv();
});

it('fails when any demo password env variable is missing', function (): void {
    createDemoUsers();
    setDemoPasswordEnv(['SEED_ADMIN_PASSWORD' => 'AdminSecurePass1']);
    unset($_ENV['SEED_INSTRUCTOR_PASSWORD'], $_SERVER['SEED_INSTRUCTOR_PASSWORD']);
    putenv('SEED_INSTRUCTOR_PASSWORD');

    $this->artisan('demo:rotate-passwords', ['--force' => true])
        ->assertFailed();

    User::where('email', 'admin@tsquare.com')->first()->refresh();

    expect(Hash::check('OldPassword123', User::where('email', 'admin@tsquare.com')->first()->password))->toBeTrue();
});

it('updates all demo accounts with force', function (): void {
    createDemoUsers();
    $passwords = setDemoPasswordEnv();

    $this->artisan('demo:rotate-passwords', ['--force' => true])
        ->assertSuccessful();

    foreach (DEMO_EMAILS as $index => $email) {
        $user = User::where('email', $email)->first();
        $envKey = DEMO_ENV_KEYS[$index];

        expect(Hash::check($passwords[$envKey], $user->password))->toBeTrue()
            ->and(Hash::check('OldPassword123', $user->password))->toBeFalse();
    }
});

it('continues updating other accounts when one demo user is missing', function (): void {
    $passwords = setDemoPasswordEnv();

    User::factory()->create([
        'email' => 'admin@tsquare.com',
        'password' => 'OldPassword123',
    ]);
    User::factory()->create([
        'email' => 'student@tsquare.com',
        'password' => 'OldPassword123',
    ]);
    User::factory()->create([
        'email' => 'receptionist@tsquare.com',
        'password' => 'OldPassword123',
    ]);

    $this->artisan('demo:rotate-passwords', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('User not found: instructor@tsquare.com');

    expect(Hash::check($passwords['SEED_ADMIN_PASSWORD'], User::where('email', 'admin@tsquare.com')->first()->password))->toBeTrue()
        ->and(User::where('email', 'instructor@tsquare.com')->exists())->toBeFalse();
});

it('fails when all demo users are missing', function (): void {
    setDemoPasswordEnv();

    $this->artisan('demo:rotate-passwords', ['--force' => true])
        ->assertFailed()
        ->expectsOutputToContain('No demo accounts were updated.');
});

it('does not update passwords when confirmation is declined', function (): void {
    createDemoUsers();
    setDemoPasswordEnv();

    $this->artisan('demo:rotate-passwords')
        ->expectsConfirmation('Are you sure you want to rotate demo account passwords?', 'no')
        ->assertFailed()
        ->expectsOutputToContain('Aborted. No passwords were changed.');

    foreach (DEMO_EMAILS as $email) {
        $user = User::where('email', $email)->first();

        expect(Hash::check('OldPassword123', $user->password))->toBeTrue();
    }
});

it('updates passwords when confirmation is accepted', function (): void {
    createDemoUsers();
    $passwords = setDemoPasswordEnv();

    $this->artisan('demo:rotate-passwords')
        ->expectsConfirmation('Are you sure you want to rotate demo account passwords?', 'yes')
        ->assertSuccessful();

    expect(Hash::check($passwords['SEED_ADMIN_PASSWORD'], User::where('email', 'admin@tsquare.com')->first()->password))->toBeTrue();
});
