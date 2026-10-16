<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\StudentAttendanceCard;
use App\Support\Attendance\PermanentCardScanOutcome;

class PermanentCardScanService
{
    private const TOKEN_PREFIX = 'card_';

    private const TOKEN_HEX_LENGTH = 64;

    public function __construct(
        private AutomaticQrStudentGuard $studentGuard,
        private PermanentCardEligibleSessionResolver $sessionResolver,
        private StudentCardAutomaticAttendanceWriter $attendanceWriter,
    ) {}

    public function scan(AttendanceDevice $device, string $qrCode): PermanentCardScanOutcome
    {
        if (! $this->isValidCardTokenFormat($qrCode)) {
            return PermanentCardScanOutcome::failure(
                error: 'Invalid QR code format.',
                code: 'INVALID_FORMAT',
                httpCode: 400,
            );
        }

        $tokenHash = StudentAttendanceCardLifecycleService::hashPlainToken($qrCode);

        $card = StudentAttendanceCard::query()
            ->where('token_hash', $tokenHash)
            ->with('student')
            ->first();

        if ($card === null || ! $card->isActive()) {
            return PermanentCardScanOutcome::failure(
                error: 'Resource not found',
                code: 'NOT_FOUND',
                httpCode: 404,
                message: 'QR code not found.',
            );
        }

        $student = $card->student;

        if ($student === null) {
            return PermanentCardScanOutcome::failure(
                error: 'Resource not found',
                code: 'NOT_FOUND',
                httpCode: 404,
                message: 'QR code not found.',
            );
        }

        if (! $this->studentGuard->isEligibleForAutomaticCheckIn($student)) {
            return PermanentCardScanOutcome::failure(
                error: 'Automatic check-in is not available for this student.',
                code: 'STUDENT_INACTIVE',
                httpCode: 403,
            );
        }

        $eligibleSessions = $this->sessionResolver->resolveEligibleSessions($student, $device);

        if ($eligibleSessions->isEmpty()) {
            return PermanentCardScanOutcome::failure(
                error: 'No eligible session found for check-in.',
                code: 'NO_ELIGIBLE_SESSION',
                httpCode: 422,
            );
        }

        if ($eligibleSessions->count() > 1) {
            return PermanentCardScanOutcome::failure(
                error: 'Multiple eligible sessions found.',
                code: 'MULTIPLE_SESSIONS',
                httpCode: 422,
            );
        }

        $session = $eligibleSessions->first();

        $record = $this->attendanceWriter->recordFromPermanentCard($session, $student);

        return PermanentCardScanOutcome::success($record);
    }

    public function isValidCardTokenFormat(string $qrCode): bool
    {
        if (! str_starts_with($qrCode, self::TOKEN_PREFIX)) {
            return false;
        }

        $payload = substr($qrCode, strlen(self::TOKEN_PREFIX));

        if (strlen($payload) !== self::TOKEN_HEX_LENGTH) {
            return false;
        }

        return ctype_xdigit($payload);
    }
}
