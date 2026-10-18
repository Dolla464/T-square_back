<?php

namespace App\Console\Commands;

use App\Models\GroupAttendanceCardBulkOperation;
use App\Models\GroupAttendanceCardBulkOperationStudent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes bulk operation metadata older than 30 days once the operation is completed.
 * Does not delete student attendance cards or touch open/processing operations.
 */
class CleanupGroupAttendanceCardBulkOperationsCommand extends Command
{
    protected $signature = 'attendance-cards:cleanup-bulk-operations {--days=30 : Retention window in days}';

    protected $description = 'Delete completed group attendance card bulk operation records older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $operations = GroupAttendanceCardBulkOperation::query()
            ->where('status', GroupAttendanceCardBulkOperation::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', $cutoff)
            ->orderBy('id')
            ->limit(200)
            ->get();

        $deleted = 0;

        foreach ($operations as $operation) {
            $hasUnresolved = GroupAttendanceCardBulkOperationStudent::query()
                ->where('group_attendance_card_bulk_operation_id', $operation->id)
                ->whereIn('status', [
                    GroupAttendanceCardBulkOperationStudent::STATUS_PENDING,
                    GroupAttendanceCardBulkOperationStudent::STATUS_PROCESSING,
                ])
                ->exists();

            if ($hasUnresolved) {
                $this->warn("Skipping operation {$operation->id}: unresolved student rows.");

                continue;
            }

            DB::transaction(function () use ($operation): void {
                DB::table('student_attendance_cards')
                    ->where('group_attendance_card_bulk_operation_id', $operation->id)
                    ->update(['group_attendance_card_bulk_operation_id' => null]);

                $operation->delete();
            });

            $deleted++;
        }

        $this->info("Removed {$deleted} completed bulk operation(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
