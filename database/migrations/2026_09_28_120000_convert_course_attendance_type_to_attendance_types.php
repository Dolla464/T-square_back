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
        Schema::table('courses', function (Blueprint $table) {
            $table->json('attendance_types')->nullable();
        });

        DB::table('courses')
            ->orderBy('id')
            ->chunkById(100, function ($courses) {
                foreach ($courses as $course) {
                    $attendanceTypes = match (strtolower($course->attendance_type ?? '')) {
                        'online' => ['online'],
                        'offline' => ['offline'],
                        'hybrid' => ['hybrid'],
                        // Production-safety fallback for unexpected/null legacy values — not a new attendance type.
                        default => ['online'],
                    };

                    DB::table('courses')
                        ->where('id', $course->id)
                        ->update(['attendance_types' => json_encode($attendanceTypes)]);
                }
            });

        Schema::table('courses', function (Blueprint $table) {
            $table->json('attendance_types')->nullable(false)->change();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['attendance_type', 'status']);
            $table->dropColumn('attendance_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('attendance_type')->nullable();
        });

        // Rollback priority for multi-type arrays: online → offline → hybrid (not JSON array order).
        $priority = ['online', 'offline', 'hybrid'];

        DB::table('courses')
            ->orderBy('id')
            ->chunkById(100, function ($courses) use ($priority) {
                foreach ($courses as $course) {
                    $types = json_decode($course->attendance_types, true);
                    $normalized = is_array($types)
                        ? array_map(static fn ($type) => strtolower((string) $type), $types)
                        : [];

                    $attendanceType = 'online';

                    foreach ($priority as $candidate) {
                        if (in_array($candidate, $normalized, true)) {
                            $attendanceType = $candidate;
                            break;
                        }
                    }

                    DB::table('courses')
                        ->where('id', $course->id)
                        ->update(['attendance_type' => $attendanceType]);
                }
            });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('attendance_type')->nullable(false)->change();
            $table->index(['attendance_type', 'status']);
            $table->dropColumn('attendance_types');
        });
    }
};
