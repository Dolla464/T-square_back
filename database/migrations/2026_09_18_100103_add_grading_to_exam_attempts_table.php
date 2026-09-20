<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->timestamp('graded_at')->nullable()->after('finished_at');
            $table->foreignId('graded_by')->nullable()->after('graded_at')
                ->constrained('instructors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropForeign(['graded_by']);
            $table->dropColumn(['graded_at', 'graded_by']);
        });
    }
};
