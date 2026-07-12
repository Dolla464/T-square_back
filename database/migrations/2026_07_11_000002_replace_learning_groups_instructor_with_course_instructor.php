<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('learning_groups', 'course_instructor_id')) {
            Schema::table('learning_groups', function (Blueprint $table) {
                $table->foreignId('course_instructor_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('course_instructor')
                    ->restrictOnDelete();
            });
        }

        $this->backfillCourseInstructorIds();

        if (Schema::hasColumn('learning_groups', 'instructor_id')) {
            Schema::table('learning_groups', function (Blueprint $table) {
                $table->dropForeign(['instructor_id']);
                $table->dropIndex(['instructor_id']);
                $table->dropColumn('instructor_id');
            });
        }

        $remaining = DB::table('learning_groups')->whereNull('course_instructor_id')->count();
        if ($remaining > 0) {
            throw new RuntimeException("{$remaining} learning group(s) still missing course_instructor_id. Fix data before migrating.");
        }

        Schema::table('learning_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('course_instructor_id')->nullable(false)->change();
            if (! $this->indexExists('learning_groups', 'learning_groups_course_instructor_id_index')) {
                $table->index('course_instructor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('learning_groups', function (Blueprint $table) {
            $table->foreignId('instructor_id')
                ->nullable()
                ->after('course_id')
                ->constrained('instructors')
                ->cascadeOnDelete();
        });

        DB::table('learning_groups')
            ->whereNotNull('course_instructor_id')
            ->orderBy('id')
            ->each(function ($group) {
                $instructorId = DB::table('course_instructor')
                    ->where('id', $group->course_instructor_id)
                    ->value('instructor_id');

                if ($instructorId) {
                    DB::table('learning_groups')
                        ->where('id', $group->id)
                        ->update(['instructor_id' => $instructorId]);
                }
            });

        Schema::table('learning_groups', function (Blueprint $table) {
            $table->dropForeign(['course_instructor_id']);
            $table->dropIndex(['course_instructor_id']);
            $table->dropColumn('course_instructor_id');
            $table->unsignedBigInteger('instructor_id')->nullable(false)->change();
            $table->index('instructor_id');
        });
    }

    private function backfillCourseInstructorIds(): void
    {
        DB::table('learning_groups')
            ->orderBy('id')
            ->each(function ($group) {
                if ($group->course_instructor_id) {
                    return;
                }

                $pivotId = null;

                if (Schema::hasColumn('learning_groups', 'instructor_id') && $group->instructor_id) {
                    $pivotId = $this->resolveOrCreatePivot($group->course_id, $group->instructor_id);
                }

                if (! $pivotId) {
                    $pivotId = DB::table('course_instructor')
                        ->where('course_id', $group->course_id)
                        ->orderBy('sort_order')
                        ->value('id');
                }

                if (! $pivotId) {
                    $instructorId = DB::table('courses')
                        ->where('id', $group->course_id)
                        ->value('instructor_id');

                    if ($instructorId) {
                        $pivotId = $this->resolveOrCreatePivot($group->course_id, $instructorId);
                    }
                }

                if ($pivotId) {
                    DB::table('learning_groups')
                        ->where('id', $group->id)
                        ->update(['course_instructor_id' => $pivotId]);
                }
            });
    }

    private function resolveOrCreatePivot(int $courseId, int $instructorId): int
    {
        $existing = DB::table('course_instructor')
            ->where('course_id', $courseId)
            ->where('instructor_id', $instructorId)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('course_instructor')->insertGetId([
            'course_id' => $courseId,
            'instructor_id' => $instructorId,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $idx) {
                if (($idx->name ?? '') === $index) {
                    return true;
                }
            }

            return false;
        }

        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return ($result[0]->count ?? 0) > 0;
    }
};
