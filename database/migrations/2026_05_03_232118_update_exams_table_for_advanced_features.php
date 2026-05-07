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
        Schema::table('exams', function (Blueprint $table) {
            $table->integer('max_attempts')->default(3)->after('duration'); // عدد المحاولات
            $table->boolean('shuffle_questions')->default(true)->after('max_attempts'); // ترتيب عشوائي
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('max_attempts');
            $table->dropColumn('shuffle_questions');
        });
    }
};
