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
        Schema::table('answers', function (Blueprint $table) {
            // إضافة الأعمدة الجديدة بعد الـ choice_id
            $table->boolean('is_correct')->default(false)->after('choice_id');
            $table->decimal('marks_earned', 10, 2)->default(0)->after('is_correct');

            // إضافة Index على is_correct لأننا هنستخدمه كتير في الـ Aggregation (Sum/Count)
            $table->index('is_correct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex(['is_correct']);
            $table->dropColumn(['is_correct', 'marks_earned']);
        });
    }
};
