<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('question_image')->nullable()->after('question_text');
            $table->longText('question_code')->nullable()->after('question_image');
            $table->string('question_code_language', 50)->nullable()->after('question_code');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['question_image', 'question_code', 'question_code_language']);
        });
    }
};
