<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->enum('status', ['ongoing', 'completed', 'timed_out'])
                ->default('ongoing')
                ->after('exam_id'); // عشان يكون الترتيب منطقي بعد الـ IDs

            // إضافة Index للـ status لأنه هيستخدم كتير في الـ Filtering
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
