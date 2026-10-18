<?php

namespace App\Services\Attendance;

use Exception;

class GroupAttendanceCardBulkException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message, $httpStatus);
    }
}
