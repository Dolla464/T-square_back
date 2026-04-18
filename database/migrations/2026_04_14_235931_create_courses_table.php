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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->string('short_description', 255);
            $table->text('description');
            $table->string('thumbnail', 255);
            $table->string('cover_image', 255)->nullable();
            $table->string('preview_video', 500)->nullable();
            $table->string('google_drive_link', 500)->nullable();
            $table->enum('attendance_type', ['Online', 'Offline', 'Hybrid']);
            $table->decimal('price_before', 10, 2);
            $table->decimal('discount_price', 10, 2)->default(0);
            $table->decimal('price', 10, 2)->nullable();
            $table->enum('level', ['beginner', 'intermediate', 'advanced']);
            $table->string('language', 50);
            $table->integer('duration_weeks');
            $table->integer('duration_hours');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_free')->default(false);

            // العلاقات
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->foreignId('instructor_id')->constrained('instructors')->onDelete('cascade');

            // إحصائيات التقييم (التعديل هنا)
            $table->decimal('avg_rating', 3, 2)->default(0); // التقييم المتوسط من 5.00
            $table->integer('reviews_count')->default(0);    // عدد المراجعات الفعلي

            // إحصائيات
            $table->integer('total_reviews')->default(0);
            $table->integer('total_students')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);

            $table->timestamp('published_at')->nullable();
            $table->softDeletes(); // عشان الـ deleted_at
            $table->timestamps();

            // الـ Indexes المركبة لتحسين الأداء
            $table->index('status');
            $table->index(['status', 'created_at']);
            $table->index(['level', 'status']);
            $table->index(['is_free', 'status']);
            $table->index(['attendance_type', 'status']);
            $table->index('is_featured');
            $table->index('price');
            $table->index('avg_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
