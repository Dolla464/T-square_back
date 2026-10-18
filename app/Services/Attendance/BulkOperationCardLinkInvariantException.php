<?php

namespace App\Services\Attendance;

use RuntimeException;

/**
 * Raised when a card row was persisted without the expected bulk-operation FK.
 * Must roll back the per-student transaction (no FAILED_PROCESSING commit with orphan card).
 */
final class BulkOperationCardLinkInvariantException extends RuntimeException
{
}
