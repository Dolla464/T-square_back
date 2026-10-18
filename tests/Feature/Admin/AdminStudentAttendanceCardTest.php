<?php

use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Models\User;
use App\Services\Attendance\StudentAttendanceCardLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['admin', 'student', 'instructor'] as $roleName) {
        Role::create(['name' => $roleName, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->studentUser = User::factory()->create();
    $this->studentUser->assignRole('student');
    $this->student = Student::factory()->create([
        'user_id' => $this->studentUser->id,
        'status' => 'active',
    ]);

    Sanctum::actingAs($this->admin, ['*']);
});

function attendanceCardUrl(Student $student, string $suffix = ''): string
{
    return '/api/admin/students/'.$student->id.'/attendance-card'.$suffix;
}

function assertResponseHasNoSensitiveTokenFields($response): void
{
    $json = json_encode($response->json());

    expect($json)->not->toMatch('/card_[0-9a-f]{64}/')
        ->and($json)->not->toContain('token_hash')
        ->and($json)->not->toContain('plain_text_token');
}

// ── Authentication and authorization ───────────────────────────────────────

it('denies guest access to all attendance card endpoints', function (): void {
    auth()->guard('sanctum')->forgetUser();

    $this->getJson(attendanceCardUrl($this->student))->assertUnauthorized();
    $this->postJson(attendanceCardUrl($this->student))->assertUnauthorized();
    $this->postJson(attendanceCardUrl($this->student, '/reissue'))->assertUnauthorized();
    $this->postJson(attendanceCardUrl($this->student, '/revoke'))->assertUnauthorized();
});

it('denies authenticated non-admin users', function (): void {
    Sanctum::actingAs($this->studentUser, ['*']);

    $this->getJson(attendanceCardUrl($this->student))->assertForbidden();
    $this->postJson(attendanceCardUrl($this->student))->assertForbidden();
    $this->postJson(attendanceCardUrl($this->student, '/reissue'))->assertForbidden();
    $this->postJson(attendanceCardUrl($this->student, '/revoke'))->assertForbidden();
});

it('denies unverified admin email with 409', function (): void {
    $unverifiedAdmin = User::factory()->create();
    $unverifiedAdmin->forceFill(['email_verified_at' => null])->save();
    $unverifiedAdmin->assignRole('admin');
    Sanctum::actingAs($unverifiedAdmin, ['*']);

    $this->getJson(attendanceCardUrl($this->student))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Your email address is not verified.');
});

// ── Status ───────────────────────────────────────────────────────────────────

it('returns no active card on status when none exists', function (): void {
    $this->getJson(attendanceCardUrl($this->student))
        ->assertOk()
        ->assertJsonPath('data.has_active_card', false)
        ->assertJsonPath('data.card', null);

    assertResponseHasNoSensitiveTokenFields($this->getJson(attendanceCardUrl($this->student)));
});

it('returns safe metadata when a card is active', function (): void {
    $issue = $this->postJson(attendanceCardUrl($this->student))->assertCreated();
    $token = $issue->json('data.plain_text_token');

    $status = $this->getJson(attendanceCardUrl($this->student))->assertOk();

    expect($status->json('data.has_active_card'))->toBeTrue()
        ->and($status->json('data.card.id'))->toBe($issue->json('data.card.id'))
        ->and($status->json('data.card.token_suffix'))->toBe(mb_substr($token, -4));

    assertResponseHasNoSensitiveTokenFields($status);
});

// ── Issue ────────────────────────────────────────────────────────────────────

it('issues a card with HTTP 201 and a valid card token', function (): void {
    $response = $this->postJson(attendanceCardUrl($this->student))->assertCreated();

    $token = $response->json('data.plain_text_token');

    expect($token)->toStartWith('card_')
        ->and(strlen($token))->toBe(5 + 64);

    $stored = StudentAttendanceCard::query()->first();

    expect($stored->token_hash)->toBe(StudentAttendanceCardLifecycleService::hashPlainToken($token))
        ->and($stored->token_suffix)->toBe(mb_substr($token, -4))
        ->and(json_encode($stored->toArray()))->not->toContain($token);
});

it('returns CARD_ALREADY_ACTIVE on duplicate issue', function (): void {
    $this->postJson(attendanceCardUrl($this->student))->assertCreated();

    $this->postJson(attendanceCardUrl($this->student))
        ->assertStatus(422)
        ->assertJsonPath('code', 'CARD_ALREADY_ACTIVE');

    expect(StudentAttendanceCard::query()->active()->where('student_id', $this->student->id)->count())->toBe(1);

    assertResponseHasNoSensitiveTokenFields(
        $this->postJson(attendanceCardUrl($this->student))->assertStatus(422)
    );
});

it('includes inactive student warning on issue without blocking issuance', function (): void {
    $this->student->forceFill(['status' => 'inactive'])->save();

    $response = $this->postJson(attendanceCardUrl($this->student))->assertCreated();

    expect($response->json('data.warnings.0.code'))->toBe('STUDENT_INACTIVE')
        ->and($response->json('data.plain_text_token'))->toStartWith('card_');
});

// ── Reissue ──────────────────────────────────────────────────────────────────

it('reissues by revoking the previous card and returning a new token', function (): void {
    $first = $this->postJson(attendanceCardUrl($this->student))->assertCreated();
    $firstToken = $first->json('data.plain_text_token');

    $second = $this->postJson(attendanceCardUrl($this->student, '/reissue'))->assertOk();
    $secondToken = $second->json('data.plain_text_token');

    expect($secondToken)->not->toBe($firstToken)
        ->and(StudentAttendanceCard::query()->active()->where('student_id', $this->student->id)->count())->toBe(1);

    assertResponseHasNoSensitiveTokenFields($this->getJson(attendanceCardUrl($this->student)));
});

it('reissues successfully when no active card exists', function (): void {
    $response = $this->postJson(attendanceCardUrl($this->student, '/reissue'))->assertOk();

    expect($response->json('data.plain_text_token'))->toStartWith('card_')
        ->and(StudentAttendanceCard::query()->active()->count())->toBe(1);
});

it('includes inactive student warning on reissue', function (): void {
    $this->student->forceFill(['status' => 'inactive'])->save();

    $this->postJson(attendanceCardUrl($this->student, '/reissue'))
        ->assertOk()
        ->assertJsonPath('data.warnings.0.code', 'STUDENT_INACTIVE');
});

it('preserves the active card when reissue persistence fails', function (): void {
    $first = $this->postJson(attendanceCardUrl($this->student))->assertCreated();
    $firstCardId = $first->json('data.card.id');

    $failingService = new class extends StudentAttendanceCardLifecycleService
    {
        protected function persistNewCard(
            Student $student,
            User $actor,
            string $tokenHash,
            string $tokenSuffix,
            ?int $bulkOperationId = null,
        ): StudentAttendanceCard {
            throw new RuntimeException('Simulated persistence failure.');
        }
    };

    $this->app->instance(StudentAttendanceCardLifecycleService::class, $failingService);

    $this->postJson(attendanceCardUrl($this->student, '/reissue'))
        ->assertStatus(500);

    $card = StudentAttendanceCard::query()->findOrFail($firstCardId);

    expect($card->revoked_at)->toBeNull()
        ->and($card->isActive())->toBeTrue();
});

// ── Revoke ───────────────────────────────────────────────────────────────────

it('revokes the active card for the student', function (): void {
    $issued = $this->postJson(attendanceCardUrl($this->student))->assertCreated();

    $response = $this->postJson(attendanceCardUrl($this->student, '/revoke'), [
        'revocation_reason' => 'Lost card',
    ])->assertOk();

    expect($response->json('data.card.revoked_at'))->not->toBeNull()
        ->and($response->json('data.card.revocation_reason'))->toBe('Lost card');

    assertResponseHasNoSensitiveTokenFields($response);

    $this->getJson(attendanceCardUrl($this->student))
        ->assertJsonPath('data.has_active_card', false);
});

it('returns NO_ACTIVE_CARD when revoking with no active card', function (): void {
    $this->postJson(attendanceCardUrl($this->student, '/revoke'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'NO_ACTIVE_CARD');
});

it('validates revocation_reason length on revoke', function (): void {
    $this->postJson(attendanceCardUrl($this->student))->assertCreated();

    $this->postJson(attendanceCardUrl($this->student, '/revoke'), [
        'revocation_reason' => str_repeat('a', 501),
    ])->assertStatus(422);
});

it('does not revoke another student card when revoking a student with no active card', function (): void {
    $otherStudent = Student::factory()->create(['status' => 'active']);

    $this->postJson(attendanceCardUrl($otherStudent))->assertCreated();

    $this->postJson(attendanceCardUrl($this->student, '/revoke'))
        ->assertStatus(422)
        ->assertJsonPath('code', 'NO_ACTIVE_CARD');

    expect(
        StudentAttendanceCard::query()->active()->where('student_id', $otherStudent->id)->exists()
    )->toBeTrue();
});

it('returns 404 for unknown student on status', function (): void {
    $this->getJson('/api/admin/students/999999/attendance-card')
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');
});

// ── Rate limiting ────────────────────────────────────────────────────────────

it('registers throttle middleware on issue and reissue routes', function (): void {
    $issueMiddleware = Route::getRoutes()->getByName('admin.students.attendance-card.issue')->gatherMiddleware();
    $reissueMiddleware = Route::getRoutes()->getByName('admin.students.attendance-card.reissue')->gatherMiddleware();

    expect(collect($issueMiddleware)->contains(fn (string $m) => str_contains($m, 'throttle:20,1')))->toBeTrue()
        ->and(collect($reissueMiddleware)->contains(fn (string $m) => str_contains($m, 'throttle:20,1')))->toBeTrue();
});

it('allows admin requests when otherwise valid', function (): void {
    $this->getJson(attendanceCardUrl($this->student))->assertOk();
    $this->postJson(attendanceCardUrl($this->student))->assertCreated();
});
