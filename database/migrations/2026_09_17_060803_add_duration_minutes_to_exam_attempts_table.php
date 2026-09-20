<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->unsignedInteger('duration_minutes')->nullable()->after('exam_id');
        });

        $attempts = DB::table('exam_attempts')
            ->join('exams', 'exam_attempts.exam_id', '=', 'exams.id')
            ->whereNull('exam_attempts.duration_minutes')
            ->select('exam_attempts.id', 'exams.duration')
            ->get();

        foreach ($attempts as $attempt) {
            DB::table('exam_attempts')
                ->where('id', $attempt->id)
                ->update(['duration_minutes' => $attempt->duration]);
        }
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
