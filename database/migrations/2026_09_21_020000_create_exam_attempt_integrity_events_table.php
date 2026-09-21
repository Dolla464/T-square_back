<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_integrity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')
                ->constrained('exam_attempts')
                ->cascadeOnDelete();
            $table->uuid('event_id');
            $table->string('event_type', 32);
            $table->dateTime('client_at')->nullable();
            $table->dateTime('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'event_id'], 'attempt_integrity_event_id_unique');
            $table->index('exam_attempt_id', 'attempt_integrity_attempt_idx');
            $table->index(['exam_attempt_id', 'event_type', 'occurred_at'], 'attempt_integrity_type_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_integrity_events');
    }
};
