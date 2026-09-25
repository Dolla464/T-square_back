<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('role', 32)->default('guest');
            $table->string('http_method', 10);
            $table->string('route_name')->nullable();
            $table->string('path', 512);
            $table->string('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_data')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('user_id');
            $table->index('role');
            $table->index('http_method');
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
