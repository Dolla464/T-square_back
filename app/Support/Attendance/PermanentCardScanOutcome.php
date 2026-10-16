<?php

namespace App\Support\Attendance;

use App\Models\AttendanceRecord;

readonly class PermanentCardScanOutcome
{
    private function __construct(
        public bool $successful,
        public ?AttendanceRecord $record,
        public ?string $error,
        public ?string $code,
        public int $httpCode,
        public ?string $message,
    ) {}

    public static function success(AttendanceRecord $record): self
    {
        return new self(
            successful: true,
            record: $record,
            error: null,
            code: null,
            httpCode: 200,
            message: null,
        );
    }

    public static function failure(
        string $error,
        string $code,
        int $httpCode,
        ?string $message = null,
    ): self {
        return new self(
            successful: false,
            record: null,
            error: $error,
            code: $code,
            httpCode: $httpCode,
            message: $message,
        );
    }
}
