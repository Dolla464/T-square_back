<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->unsignedInteger('time_spent_seconds')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->dropColumn('time_spent_seconds');
        });
    }
};
