<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_exam_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('learning_group_id')->constrained('learning_groups')->cascadeOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('instructors')->nullOnDelete();
            $table->timestamp('activated_at')->useCurrent();
            $table->unique(['exam_id', 'learning_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_exam_activations');
    }
};
