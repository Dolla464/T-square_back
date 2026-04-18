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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->string('billing_name', 255);
            $table->string('billing_email', 255);
            $table->string('billing_phone', 20);
            $table->text('notes')->nullable();
            $table->timestamps();

            // الـ Indexes  لتحسين تقارير المبيعات
            $table->index('student_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['created_at', 'status']);
            $table->index('billing_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
