<?php

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->student = Student::factory()->create([
        'age' => 20,
        'qualification' => 'High School',
        'guardian_phone' => '01112345678',
        'national_id' => '12345678901234',
        'address' => 'Old address',
        'notes' => 'Old notes',
    ]);
    $this->student->user->assignRole('student');
    Sanctum::actingAs($this->student->user, ['*']);
});

it('returns student profile fields on profile show', function (): void {
    $response = $this->getJson('/api/profile');

    $response->assertOk()
        ->assertJsonPath('data.student.age', 20)
        ->assertJsonPath('data.student.qualification', 'High School')
        ->assertJsonPath('data.student.guardian_phone', '01112345678')
        ->assertJsonPath('data.student.national_id', '12345678901234')
        ->assertJsonPath('data.student.address', 'Old address')
        ->assertJsonPath('data.student.notes', 'Old notes');
});

it('updates student profile fields from profile endpoint', function (): void {
    $response = $this->postJson('/api/profile', [
        'age' => 25,
        'qualification' => 'Bachelor Degree',
        'guardian_phone' => '01198765432',
        'address' => 'New address in Giza',
        'notes' => 'Updated notes from student',
    ]);

    $response->assertOk();

    $fresh = $this->student->fresh();

    expect($fresh->age)->toBe(25)
        ->and($fresh->qualification)->toBe('Bachelor Degree')
        ->and($fresh->guardian_phone)->toBe('01198765432')
        ->and($fresh->national_id)->toBe('12345678901234')
        ->and($fresh->address)->toBe('New address in Giza')
        ->and($fresh->notes)->toBe('Updated notes from student');
});

it('rejects invalid national id on student profile update', function (): void {
    $response = $this->postJson('/api/profile', [
        'national_id' => '123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['national_id']);
});
