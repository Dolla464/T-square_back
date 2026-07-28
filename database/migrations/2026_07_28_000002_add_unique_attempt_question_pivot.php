<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateGroups = DB::table('attempt_questions')
            ->select('exam_attempt_id', 'question_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('exam_attempt_id', 'question_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            DB::table('attempt_questions')
                ->where('exam_attempt_id', $group->exam_attempt_id)
                ->where('question_id', $group->question_id)
                ->where('id', '!=', $group->keep_id)
                ->delete();
        }

        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->unique(['exam_attempt_id', 'question_id'], 'attempt_questions_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attempt_questions', function (Blueprint $table) {
            $table->dropUnique('attempt_questions_attempt_question_unique');
        });
    }
};
