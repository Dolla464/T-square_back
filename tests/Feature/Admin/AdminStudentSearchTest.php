<?php

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);
});

it('finds students by enrollment number in admin index search', function (): void {
    $student = Student::factory()->create([
        'full_name' => 'Unique Student Alpha',
        'status' => 'active',
    ]);
    $student->forceFill(['enrollment_number' => 'ENR-99001'])->save();

    Student::factory()->count(3)->create(['status' => 'active']);

    $this->getJson('/api/admin/students?search=ENR-99001&status=active&for_select=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $student->id)
        ->assertJsonCount(1, 'data');
});

it('finds students by phone in admin index search', function (): void {
    $student = Student::factory()->create([
        'full_name' => 'Phone Search Target',
        'phone' => '01099887766',
        'status' => 'active',
    ]);

    Student::factory()->count(2)->create(['status' => 'active']);

    $this->getJson('/api/admin/students?search=01099887766&status=active&for_select=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $student->id);
});

it('returns lightweight student payload when for_select is enabled', function (): void {
    Student::factory()->create(['status' => 'active']);

    $response = $this->getJson('/api/admin/students?for_select=1&per_page=5&status=active')
        ->assertOk();

    $first = $response->json('data.0');
    expect($first)->toHaveKeys(['id', 'full_name', 'email', 'phone', 'enrollment_number']);
    expect($first)->not->toHaveKey('enrolled_courses');
});
