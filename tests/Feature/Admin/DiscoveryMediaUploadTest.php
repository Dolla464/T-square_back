<?php

/**
 * Feature tests for discovery media bulk upload.
 *
 * Route: POST /api/admin/website-media/upload
 */

use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\AdminSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    Storage::fake('public');

    Setting::set('discovery_media', [], 'json', 'general');
});

function discoveryUpload(array $files, string $action = 'append'): \Illuminate\Testing\TestResponse
{
    return test()->post('/api/admin/website-media/upload', [
        'key' => 'discovery_media',
        'action' => $action,
        'images' => $files,
    ]);
}

it('keeps old discovery images when a replace batch fails', function (): void {
    Storage::disk('public')->put('discovery/old.webp', 'old-image-content');
    $oldUrl = Storage::url('discovery/old.webp');

    Setting::set('discovery_media', [$oldUrl], 'json', 'general');

    $response = discoveryUpload([
        UploadedFile::fake()->create('broken.jpg', 50, 'image/jpeg'),
    ], 'replace');

    $response->assertServerError();

    expect(Storage::disk('public')->exists('discovery/old.webp'))->toBeTrue();
    expect(Setting::get('discovery_media'))->toBe([$oldUrl]);
});

it('rejects append when discovery gallery would exceed 30 images', function (): void {
    $existing = array_map(
        fn (int $i) => Storage::url("discovery/existing-{$i}.webp"),
        range(1, 28)
    );

    Setting::set('discovery_media', $existing, 'json', 'general');

    $response = discoveryUpload([
        UploadedFile::fake()->image('one.jpg', 800, 600),
        UploadedFile::fake()->image('two.jpg', 800, 600),
        UploadedFile::fake()->image('three.jpg', 800, 600),
        UploadedFile::fake()->image('four.jpg', 800, 600),
        UploadedFile::fake()->image('five.jpg', 800, 600),
    ], 'append');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['images']);
});

it('rolls back a failed discovery batch without leaving orphan files', function (): void {
    $response = discoveryUpload([
        UploadedFile::fake()->image('valid.jpg', 800, 600),
        UploadedFile::fake()->create('broken.jpg', 50, 'image/jpeg'),
    ], 'append');

    $response->assertServerError();

    expect(collect(Storage::disk('public')->allFiles('discovery')))->toHaveCount(0);
    expect(Setting::get('discovery_media'))->toBe([]);
});

it('stores discovery uploads as resized webp files', function (): void {
    $response = discoveryUpload([
        UploadedFile::fake()->image('large.jpg', 1600, 1200),
    ], 'replace');

    $response->assertOk();

    $images = Setting::get('discovery_media');
    expect($images)->toHaveCount(1);

    preg_match('#/storage/(.+)$#', $images[0], $matches);
    $relativePath = $matches[1] ?? ltrim(str_replace('/storage/', '', $images[0]), '/');

    expect(Storage::disk('public')->exists($relativePath))->toBeTrue();
    expect(str_ends_with($relativePath, '.webp'))->toBeTrue();

    $absolutePath = Storage::disk('public')->path($relativePath);
    $info = getimagesize($absolutePath);

    expect($info)->not->toBeFalse();
    expect($info[0])->toBeLessThanOrEqual(AdminSettingService::DISCOVERY_MEDIA_MAX_SIZE);
    expect($info[1])->toBeLessThanOrEqual(AdminSettingService::DISCOVERY_MEDIA_MAX_SIZE);
});

it('deletes old discovery images only after a successful replace batch', function (): void {
    Storage::disk('public')->put('discovery/old.webp', 'old-image-content');
    $oldUrl = Storage::url('discovery/old.webp');

    Setting::set('discovery_media', [$oldUrl], 'json', 'general');

    $response = discoveryUpload([
        UploadedFile::fake()->image('new.jpg', 1200, 900),
    ], 'replace');

    $response->assertOk();

    expect(Storage::disk('public')->exists('discovery/old.webp'))->toBeFalse();
    expect(Setting::get('discovery_media'))->not->toContain($oldUrl);
});
