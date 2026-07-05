<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes for order/enrollment filtering used by groups and revenue reports.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'total_amount'], 'orders_status_amount_index');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['course_id', 'order_id'], 'enrollments_course_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_amount_index');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_course_order_index');
        });
    }
};
