<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->date('override_date')->nullable()->after('session_date');
            $table->time('override_start_time')->nullable()->after('override_date');
            $table->time('override_end_time')->nullable()->after('override_start_time');
            $table->text('cancellation_reason')->nullable()->after('override_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn(['override_date', 'override_start_time', 'override_end_time', 'cancellation_reason']);
        });
    }
};
