<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('attendance_type')->nullable()->after('group_id');
        });

        DB::table('enrollments')
            ->orderBy('id')
            ->chunkById(100, function ($enrollments) {
                foreach ($enrollments as $enrollment) {
                    $attendanceTypesJson = DB::table('courses')
                        ->where('id', $enrollment->course_id)
                        ->value('attendance_types');

                    $attendanceTypes = json_decode($attendanceTypesJson, true);

                    if (! is_array($attendanceTypes) || count($attendanceTypes) !== 1) {
                        continue;
                    }

                    $attendanceType = strtolower(trim((string) $attendanceTypes[0]));

                    if (! in_array($attendanceType, ['online', 'offline', 'hybrid'], true)) {
                        continue;
                    }

                    DB::table('enrollments')
                        ->where('id', $enrollment->id)
                        ->update(['attendance_type' => $attendanceType]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('attendance_type');
        });
    }
};
