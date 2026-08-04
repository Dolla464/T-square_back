<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('question_id');
        });
    }

    public function down(): void
    {
        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
