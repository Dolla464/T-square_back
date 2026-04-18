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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->integer('duration')->comment('Duration in minutes');
            $table->decimal('total_marks', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->softDeletes(); // ده بيكريت عمود deleted_at
            $table->timestamps();

            // الفهارس (Indexes)
            $table->index('course_id');
            $table->index('is_active');
            $table->index(['deleted_at', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
