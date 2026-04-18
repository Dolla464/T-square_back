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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');

            // ربطها بالأوردر (ممكن يكون null لو الكورس مجاني أو يدوي من الأدمن)
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');

            $table->decimal('price_paid', 10, 2);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // الفهارس (Indexes)
            $table->unique(['student_id', 'course_id'], 'student_course_unique');
            $table->index('is_completed');
            $table->index(['course_id', 'is_completed']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
