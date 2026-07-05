<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->index('status_changed_at');
        });

        DB::table('orders')->orderBy('id')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                $statusChangedAt = $order->status === 'pending'
                    ? $order->created_at
                    : $order->updated_at;

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['status_changed_at' => $statusChangedAt]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status_changed_at']);
            $table->dropColumn('status_changed_at');
        });
    }
};
