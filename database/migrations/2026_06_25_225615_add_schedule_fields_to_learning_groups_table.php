<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_groups', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('instructor_id');
            $table->date('end_date')->nullable()->after('start_date');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active')->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('learning_groups', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'status']);
        });
    }
};
