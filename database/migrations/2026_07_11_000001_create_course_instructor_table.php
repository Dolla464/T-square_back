<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_instructor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('instructors')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['course_id', 'instructor_id']);
            $table->index('course_id');
            $table->index('instructor_id');
        });

        DB::table('courses')
            ->whereNotNull('instructor_id')
            ->orderBy('id')
            ->each(function ($course) {
                DB::table('course_instructor')->insert([
                    'course_id' => $course->id,
                    'instructor_id' => $course->instructor_id,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_instructor');
    }
};
