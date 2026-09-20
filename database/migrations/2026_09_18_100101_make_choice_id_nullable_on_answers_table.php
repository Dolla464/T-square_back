<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropForeign(['choice_id']);
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->unsignedBigInteger('choice_id')->nullable()->change();
            $table->foreign('choice_id')->references('id')->on('choices')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropForeign(['choice_id']);
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->unsignedBigInteger('choice_id')->nullable(false)->change();
            $table->foreign('choice_id')->references('id')->on('choices')->onDelete('cascade');
        });
    }
};
