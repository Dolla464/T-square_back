<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_groups', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('course_id')
                ->constrained('branches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('learning_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
