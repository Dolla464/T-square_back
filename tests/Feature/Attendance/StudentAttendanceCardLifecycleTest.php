<?php

use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Models\User;
use App\Services\Attendance\DTO\IssuedStudentAttendanceCard;
use App\Services\Attendance\StudentAttendanceCardLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'student', 'guard_name' => 'web']);

    $this->service = app(StudentAttendanceCardLifecycleService::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->studentUser = User::factory()->create();
    $this->studentUser->assignRole('student');
    $this->student = Student::factory()->create(['user_id' => $this->studentUser->id]);
});

it('issues a card and returns a usable raw token', function (): void {
    $issued = $this->service->issue($this->admin, $this->student);

    expect($issued)->toBeInstanceOf(IssuedStudentAttendanceCard::class)
        ->and($issued->plainTextToken)->toStartWith('card_')
        ->and(strlen($issued->plainTextToken))->toBe(5 + 64)
        ->and($issued->card->student_id)->toBe($this->student->id)
        ->and($issued->card->isActive())->toBeTrue();
});

it('persists only the token hash and suffix, never the raw token', function (): void {
    $issued = $this->service->issue($this->admin, $this->student);

    $stored = StudentAttendanceCard::query()->findOrFail($issued->card->id);

    expect($stored->token_hash)->toBe(
        StudentAttendanceCardLifecycleService::hashPlainToken($issued->plainTextToken)
    )
        ->and($stored->token_suffix)->toBe(mb_substr($issued->plainTextToken, -4))
        ->and($stored->getAttributes())->not->toHaveKey('plain_text_token');

    $json = json_encode($stored->toArray());
    expect($json)->not->toContain($issued->plainTextToken);
});

it('generates distinct tokens across issuances after reissue', function (): void {
    $first = $this->service->issue($this->admin, $this->student);
    $second = $this->service->reissue($this->admin, $this->student);

    expect($first->plainTextToken)->not->toBe($second->plainTextToken)
        ->and($first->card->token_hash)->not->toBe($second->card->token_hash);
});

it('revokes a card without deleting history', function (): void {
    $issued = $this->service->issue($this->admin, $this->student);

    $revoked = $this->service->revoke($this->admin, $issued->card, 'Lost card');

    expect($revoked->revoked_at)->not->toBeNull()
        ->and($revoked->revoked_by_user_id)->toBe($this->admin->id)
        ->and($revoked->revocation_reason)->toBe('Lost card')
        ->and($revoked->isActive())->toBeFalse()
        ->and(StudentAttendanceCard::query()->count())->toBe(1);
});

it('handles repeated revocation idempotently', function (): void {
    $issued = $this->service->issue($this->admin, $this->student);
    $firstRevoke = $this->service->revoke($this->admin, $issued->card, 'First reason');
    $secondRevoke = $this->service->revoke($this->admin, $firstRevoke, 'Second reason');

    expect($secondRevoke->revoked_at->equalTo($firstRevoke->revoked_at))->toBeTrue()
        ->and($secondRevoke->revocation_reason)->toBe('First reason')
        ->and($secondRevoke->revoked_by_user_id)->toBe($this->admin->id);
});

it('reissues by revoking the previous card and issuing a new active card', function (): void {
    $first = $this->service->issue($this->admin, $this->student);
    $second = $this->service->reissue($this->admin, $this->student, 'Replaced card');

    $first->card->refresh();

    expect($first->card->revoked_at)->not->toBeNull()
        ->and($first->card->revocation_reason)->toBe('Replaced card')
        ->and($second->card->isActive())->toBeTrue()
        ->and(StudentAttendanceCard::query()->count())->toBe(2)
        ->and($this->service->findActiveCardForStudent($this->student)?->id)->toBe($second->card->id);
});

it('rolls back reissue when persistence fails so the prior card stays active', function (): void {
    $first = $this->service->issue($this->admin, $this->student);

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

    expect(fn () => $failingService->reissue($this->admin, $this->student))
        ->toThrow(RuntimeException::class, 'Simulated persistence failure.');

    $first->card->refresh();

    expect($first->card->revoked_at)->toBeNull()
        ->and($this->service->findActiveCardForStudent($this->student)?->id)->toBe($first->card->id);
});

it('refuses issue when the student already has an active card', function (): void {
    $this->service->issue($this->admin, $this->student);

    expect(fn () => $this->service->issue($this->admin, $this->student))
        ->toThrow(InvalidArgumentException::class, 'Student already has an active attendance card.');

    expect(StudentAttendanceCard::query()->active()->where('student_id', $this->student->id)->count())->toBe(1);
});

it('refuses issue when multiple active cards already exist', function (): void {
    StudentAttendanceCard::factory()->count(2)->create([
        'student_id' => $this->student->id,
        'issued_by_user_id' => $this->admin->id,
    ]);

    expect(StudentAttendanceCard::query()->active()->where('student_id', $this->student->id)->count())->toBe(2);

    expect(fn () => $this->service->issue($this->admin, $this->student))
        ->toThrow(InvalidArgumentException::class, 'Student already has an active attendance card.');
});

it('reissues by revoking every active card when multiple active records exist', function (): void {
    $older = StudentAttendanceCard::factory()->create([
        'student_id' => $this->student->id,
        'issued_at' => now()->subDay(),
        'issued_by_user_id' => $this->admin->id,
    ]);
    $newer = StudentAttendanceCard::factory()->create([
        'student_id' => $this->student->id,
        'issued_at' => now(),
        'issued_by_user_id' => $this->admin->id,
    ]);

    $replacement = $this->service->reissue($this->admin, $this->student, 'Consolidated reissue');

    $older->refresh();
    $newer->refresh();

    expect($older->revoked_at)->not->toBeNull()
        ->and($newer->revoked_at)->not->toBeNull()
        ->and($older->revocation_reason)->toBe('Consolidated reissue')
        ->and($newer->revocation_reason)->toBe('Consolidated reissue')
        ->and(StudentAttendanceCard::query()->active()->where('student_id', $this->student->id)->count())->toBe(1)
        ->and($replacement->card->isActive())->toBeTrue()
        ->and(StudentAttendanceCard::query()->where('student_id', $this->student->id)->count())->toBe(3);
});

it('rolls back all revocations when reissue fails with multiple active cards present', function (): void {
    $first = StudentAttendanceCard::factory()->create([
        'student_id' => $this->student->id,
        'issued_at' => now()->subDay(),
        'issued_by_user_id' => $this->admin->id,
    ]);
    $second = StudentAttendanceCard::factory()->create([
        'student_id' => $this->student->id,
        'issued_at' => now(),
        'issued_by_user_id' => $this->admin->id,
    ]);

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

    expect(fn () => $failingService->reissue($this->admin, $this->student))
        ->toThrow(RuntimeException::class, 'Simulated persistence failure.');

    $first->refresh();
    $second->refresh();

    expect($first->revoked_at)->toBeNull()
        ->and($second->revoked_at)->toBeNull()
        ->and(StudentAttendanceCard::query()->active()->where('student_id', $this->student->id)->count())->toBe(2);
});

it('rejects lifecycle operations from a null actor', function (): void {
    expect(fn () => $this->service->issue(null, $this->student))
        ->toThrow(AuthorizationException::class);

    $issued = $this->service->issue($this->admin, $this->student);

    expect(fn () => $this->service->revoke(null, $issued->card))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $this->service->reissue(null, $this->student))
        ->toThrow(AuthorizationException::class);
});

it('rejects issuance and revocation from non-admin users', function (): void {
    expect(fn () => $this->service->issue($this->studentUser, $this->student))
        ->toThrow(AuthorizationException::class);

    $issued = $this->service->issue($this->admin, $this->student);

    expect(fn () => $this->service->revoke($this->studentUser, $issued->card))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $this->service->reissue($this->studentUser, $this->student))
        ->toThrow(AuthorizationException::class);
});

it('prevents deleting a student while attendance card history exists', function (): void {
    $this->service->issue($this->admin, $this->student);

    expect(fn () => $this->student->delete())->toThrow(QueryException::class);
});

it('treats revoked cards as inactive in the lifecycle service', function (): void {
    $issued = $this->service->issue($this->admin, $this->student);
    $this->service->revoke($this->admin, $issued->card);

    expect($this->service->findActiveCardForStudent($this->student))->toBeNull();
});
