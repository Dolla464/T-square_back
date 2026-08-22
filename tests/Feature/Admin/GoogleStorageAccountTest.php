<?php

use App\Models\GoogleStorageAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    Sanctum::actingAs($this->admin, ['*']);
});

it('lists google storage accounts without exposing tokens', function () {
    GoogleStorageAccount::factory()->create([
        'name' => 'Primary Drive',
    ]);

    $response = $this->getJson('/api/admin/google-storage-accounts')->assertOk();

    $json = json_encode($response->json());
    expect($json)->not->toContain('test-access-token');
    expect($json)->not->toContain('test-refresh-token');
    expect($response->json('data.0.name'))->toBe('Primary Drive');
});

it('creates a pending google storage account', function () {
    $response = $this->postJson('/api/admin/google-storage-accounts', [
        'name' => 'Course Videos Account',
    ])->assertCreated();

    expect($response->json('data.status'))->toBe('pending');
});
