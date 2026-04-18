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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->string('full_name', 255);
            $table->string('phone', 20)->unique()->nullable();
            $table->string('enrollment_number', 50)->unique(); // رقم القيد أو الكارنيه
            $table->foreignId('group_id')->nullable()->constrained('learning_groups')->onDelete('set null');
            $table->string('avatar', 255)->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();

            // الفهارس الإضافية
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
