<?php

namespace App\Services\Attendance;

use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Models\User;
use App\Services\Attendance\DTO\IssuedStudentAttendanceCard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class StudentAttendanceCardLifecycleService
{
    private const TOKEN_PREFIX = 'card_';

    private const TOKEN_BYTES = 32;

    public function issue(?User $actor, Student $student): IssuedStudentAttendanceCard
    {
        $this->assertAdministrator($actor);

        return DB::transaction(function () use ($actor, $student) {
            $lockedStudent = Student::query()->lockForUpdate()->find($student->id);

            if ($lockedStudent === null) {
                throw (new ModelNotFoundException)->setModel(Student::class, [$student->id]);
            }

            return $this->issueOnLockedStudent($actor, $lockedStudent);
        });
    }

    /**
     * Issue within an outer transaction after the student row is locked.
     *
     * @param  int|null  $bulkOperationId  Durable link to a bulk operation row when applicable.
     */
    public function issueOnLockedStudent(
        ?User $actor,
        Student $lockedStudent,
        ?int $bulkOperationId = null,
    ): IssuedStudentAttendanceCard {
        $this->assertAdministrator($actor);

        if ($this->studentHasActiveCards($lockedStudent)) {
            throw new InvalidArgumentException('Student already has an active attendance card.');
        }

        return $this->createAndPersistCard($lockedStudent, $actor, $bulkOperationId);
    }

    public function revoke(
        ?User $actor,
        StudentAttendanceCard $card,
        ?string $reason = null
    ): StudentAttendanceCard {
        $this->assertAdministrator($actor);

        return DB::transaction(function () use ($actor, $card, $reason) {
            $lockedCard = StudentAttendanceCard::query()->lockForUpdate()->find($card->id);

            if ($lockedCard === null) {
                throw (new ModelNotFoundException)->setModel(StudentAttendanceCard::class, [$card->id]);
            }

            if ($lockedCard->revoked_at !== null) {
                return $lockedCard;
            }

            $lockedCard->update([
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
                'revocation_reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
            ]);

            return $lockedCard->fresh();
        });
    }

    public function reissue(
        ?User $actor,
        Student $student,
        ?string $revokeReason = null
    ): IssuedStudentAttendanceCard {
        $this->assertAdministrator($actor);

        return DB::transaction(function () use ($actor, $student, $revokeReason) {
            $lockedStudent = Student::query()->lockForUpdate()->find($student->id);

            if ($lockedStudent === null) {
                throw (new ModelNotFoundException)->setModel(Student::class, [$student->id]);
            }

            return $this->reissueOnLockedStudent(
                $actor,
                $lockedStudent,
                $revokeReason,
                null,
            );
        });
    }

    /**
     * Reissue within an outer transaction after the student row is locked.
     */
    public function reissueOnLockedStudent(
        ?User $actor,
        Student $lockedStudent,
        ?string $revokeReason = null,
        ?int $bulkOperationId = null,
    ): IssuedStudentAttendanceCard {
        $this->assertAdministrator($actor);

        $this->revokeAllActiveCardsForStudent(
            $lockedStudent,
            $actor,
            $revokeReason ?? 'Superseded by reissue.'
        );

        return $this->createAndPersistCard($lockedStudent, $actor, $bulkOperationId);
    }

    public function findActiveCardForStudent(Student $student): ?StudentAttendanceCard
    {
        return StudentAttendanceCard::query()
            ->where('student_id', $student->id)
            ->active()
            ->orderByDesc('issued_at')
            ->first();
    }

    public static function hashPlainToken(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }

    protected function persistNewCard(
        Student $student,
        User $actor,
        string $tokenHash,
        string $tokenSuffix,
        ?int $bulkOperationId = null,
    ): StudentAttendanceCard {
        return StudentAttendanceCard::query()->create([
            'student_id' => $student->id,
            'token_hash' => $tokenHash,
            'token_suffix' => $tokenSuffix,
            'issued_at' => now(),
            'issued_by_user_id' => $actor->id,
            'group_attendance_card_bulk_operation_id' => $bulkOperationId,
        ]);
    }

    private function createAndPersistCard(
        Student $student,
        User $actor,
        ?int $bulkOperationId = null,
    ): IssuedStudentAttendanceCard {
        $plainTextToken = $this->generatePlainTextToken();
        $tokenHash = self::hashPlainToken($plainTextToken);
        $tokenSuffix = mb_substr($plainTextToken, -4);

        $card = $this->persistNewCard($student, $actor, $tokenHash, $tokenSuffix, $bulkOperationId);

        return new IssuedStudentAttendanceCard($card, $plainTextToken);
    }

    private function studentHasActiveCards(Student $student): bool
    {
        return StudentAttendanceCard::query()
            ->where('student_id', $student->id)
            ->active()
            ->exists();
    }

    private function revokeAllActiveCardsForStudent(
        Student $student,
        User $actor,
        ?string $reason
    ): void {
        $activeCards = StudentAttendanceCard::query()
            ->where('student_id', $student->id)
            ->active()
            ->lockForUpdate()
            ->orderByDesc('issued_at')
            ->get();

        foreach ($activeCards as $activeCard) {
            $this->revokeCardRecord($activeCard, $actor, $reason);
        }
    }

    private function revokeCardRecord(
        StudentAttendanceCard $card,
        User $actor,
        ?string $reason
    ): void {
        if ($card->revoked_at !== null) {
            return;
        }

        $card->update([
            'revoked_at' => now(),
            'revoked_by_user_id' => $actor->id,
            'revocation_reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
        ]);
    }

    private function generatePlainTextToken(): string
    {
        $attempts = 0;

        do {
            $plainTextToken = self::TOKEN_PREFIX.bin2hex(random_bytes(self::TOKEN_BYTES));
            $hash = self::hashPlainToken($plainTextToken);
            $attempts++;

            if ($attempts > 5) {
                throw new RuntimeException('Unable to generate a unique attendance card token.');
            }
        } while (StudentAttendanceCard::query()->where('token_hash', $hash)->exists());

        return $plainTextToken;
    }

    private function assertAdministrator(?User $actor): void
    {
        if ($actor === null || ! $actor->hasRole('admin')) {
            throw new AuthorizationException('Only administrators may manage student attendance cards.');
        }
    }
}
