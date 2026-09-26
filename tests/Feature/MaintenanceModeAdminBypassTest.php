<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->student = User::factory()->create();
    $this->student->assignRole('student');
});

function setMaintenanceMode(bool $enabled): void
{
    Setting::set('maintenance_mode', $enabled ? '1' : '0', 'boolean', 'general');
}

it('blocks guest from /api/user when maintenance is on', function (): void {
    setMaintenanceMode(true);

    $this->getJson('/api/user')
        ->assertStatus(503);
});

it('blocks student from /api/user when maintenance is on', function (): void {
    setMaintenanceMode(true);
    Sanctum::actingAs($this->student, ['*']);

    $this->getJson('/api/user')
        ->assertStatus(503);
});

it('allows admin session access to /api/user when maintenance is on', function (): void {
    setMaintenanceMode(true);
    $this->actingAs($this->admin);

    $this->getJson('/api/user')
        ->assertOk();
});

it('allows admin bearer access to /api/user when maintenance is on', function (): void {
    setMaintenanceMode(true);
    Sanctum::actingAs($this->admin, ['*']);

    $this->getJson('/api/user')
        ->assertOk();
});

it('allows admin access to admin maintenance status endpoint when maintenance is on', function (): void {
    setMaintenanceMode(true);
    Sanctum::actingAs($this->admin, ['*']);

    $this->getJson('/api/admin/settings/maintenance-status')
        ->assertOk();
});

it('allows student access to /api/user when maintenance is off', function (): void {
    setMaintenanceMode(false);
    Sanctum::actingAs($this->student, ['*']);

    $this->getJson('/api/user')
        ->assertOk();
});

it('keeps public maintenance endpoint accessible when maintenance is on', function (): void {
    setMaintenanceMode(true);

    $this->getJson('/api/settings/maintenance_mode')
        ->assertOk()
        ->assertJsonPath('data.value', true);
});

it('keeps public maintenance endpoint accessible when maintenance is off', function (): void {
    setMaintenanceMode(false);

    $this->getJson('/api/settings/maintenance_mode')
        ->assertOk()
        ->assertJsonPath('data.value', false);
});

it('allows admin access to notifications outside /api/admin when maintenance is on', function (): void {
    setMaintenanceMode(true);
    Sanctum::actingAs($this->admin, ['*']);

    $this->getJson('/api/notifications')
        ->assertOk();
});
