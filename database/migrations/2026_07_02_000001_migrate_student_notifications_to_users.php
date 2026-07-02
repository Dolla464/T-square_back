<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $notifications = DB::table('notifications')
            ->where('notifiable_type', 'App\\Models\\Student')
            ->get(['id', 'notifiable_id']);

        foreach ($notifications as $notification) {
            $userId = DB::table('students')
                ->where('id', $notification->notifiable_id)
                ->value('user_id');

            if (! $userId) {
                continue;
            }

            DB::table('notifications')
                ->where('id', $notification->id)
                ->update([
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $userId,
                ]);
        }
    }

    public function down(): void
    {
        // Irreversible: original Student ownership cannot be restored reliably.
    }
};
