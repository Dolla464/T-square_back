<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->text('answer_text')->nullable()->after('choice_id');
            $table->timestamp('graded_at')->nullable()->after('marks_earned');
            $table->foreignId('graded_by')->nullable()->after('graded_at')
                ->constrained('instructors')->nullOnDelete();
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->boolean('is_correct')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->boolean('is_correct')->default(false)->nullable(false)->change();
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropForeign(['graded_by']);
            $table->dropColumn(['answer_text', 'graded_at', 'graded_by']);
        });
    }
};
