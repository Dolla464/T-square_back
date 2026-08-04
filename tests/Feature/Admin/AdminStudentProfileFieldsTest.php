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
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);
});

function validAdminStudentPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Ahmed Mohamed Ali',
        'email' => 'student-'.uniqid().'@example.com',
        'password' => 'Password123',
        'phone' => '010'.fake()->unique()->numerify('########'),
        'role' => 'student',
        'gender' => 'male',
        'age' => 22,
        'qualification' => 'Bachelor Degree',
        'guardian_phone' => '011'.fake()->numerify('########'),
        'national_id' => fake()->unique()->numerify('##############'),
        'address' => 'Cairo, Egypt',
        'notes' => 'Needs evening sessions',
    ], $overrides);
}

it('stores optional student profile fields when admin creates a student', function (): void {
    $payload = validAdminStudentPayload();

    $response = $this->postJson('/api/admin/users', $payload);

    $response->assertCreated();

    $student = Student::query()->whereHas('user', fn ($q) => $q->where('email', $payload['email']))->first();

    expect($student)->not->toBeNull()
        ->and($student->age)->toBe(22)
        ->and($student->qualification)->toBe('Bachelor Degree')
        ->and($student->guardian_phone)->toBe($payload['guardian_phone'])
        ->and($student->national_id)->toBe($payload['national_id'])
        ->and($student->address)->toBe('Cairo, Egypt')
        ->and($student->notes)->toBe('Needs evening sessions');
});

it('updates student national id when admin sends 14 digits', function (): void {
    $student = Student::factory()->create(['national_id' => null]);
    $newNationalId = fake()->unique()->numerify('##############');

    $response = $this->postJson("/api/admin/students/{$student->id}", [
        'national_id' => $newNationalId,
    ]);

    $response->assertOk();

    expect($student->fresh()->national_id)->toBe($newNationalId);
});

it('rejects invalid national id length on admin student update', function (): void {
    $student = Student::factory()->create();

    $response = $this->postJson("/api/admin/students/{$student->id}", [
        'national_id' => '1234567890123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['national_id']);
});

it('rejects duplicate national id on admin student update', function (): void {
    $existing = Student::factory()->create(['national_id' => '12345678901234']);
    $student = Student::factory()->create(['national_id' => null]);

    $response = $this->postJson("/api/admin/students/{$student->id}", [
        'national_id' => $existing->national_id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['national_id']);
});
