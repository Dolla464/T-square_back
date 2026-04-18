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
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->string('full_name', 255);
            $table->string('phone', 20)->unique()->nullable();
            $table->string('avatar', 255)->nullable();
            $table->text('bio');
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('insta_url', 255)->nullable();
            $table->string('linkedin_url', 255)->nullable();
            $table->string('facebook_url', 255)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->decimal('avg_rating', 3, 2)->default(0); // متوسط تقييمه في كل كورساته
            $table->integer('reviews_count')->default(0);    // إجمالي عدد المقييمين له
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructors');
    }
};
