<?php

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    $this->instructor = Instructor::factory()->create();
    $this->instructor->user->update(['password' => 'OldPassword123']);
});

it('updates instructor password when admin sends password and confirmation', function (): void {
    $response = $this->postJson("/api/admin/instructors/{$this->instructor->id}", [
        'password' => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ]);

    $response->assertOk();

    $this->instructor->user->refresh();

    expect(Hash::check('NewPassword123', $this->instructor->user->password))->toBeTrue()
        ->and(Hash::check('OldPassword123', $this->instructor->user->password))->toBeFalse();
});

it('rejects instructor password update when confirmation is missing', function (): void {
    $response = $this->postJson("/api/admin/instructors/{$this->instructor->id}", [
        'password' => 'NewPassword123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    $this->instructor->user->refresh();

    expect(Hash::check('OldPassword123', $this->instructor->user->password))->toBeTrue();
});

it('keeps instructor password unchanged when admin updates without password', function (): void {
    $response = $this->postJson("/api/admin/instructors/{$this->instructor->id}", [
        'full_name' => 'Updated Instructor Name Here',
    ]);

    $response->assertOk();

    $this->instructor->user->refresh();

    expect(Hash::check('OldPassword123', $this->instructor->user->password))->toBeTrue();
});
