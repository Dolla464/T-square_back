<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedTinyInteger('age')->nullable()->after('gender');
            $table->string('qualification', 255)->nullable()->after('age');
            $table->string('guardian_phone', 20)->nullable()->after('qualification');
            $table->char('national_id', 14)->nullable()->unique()->after('guardian_phone');
            $table->text('address')->nullable()->after('national_id');
            $table->text('notes')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['national_id']);
            $table->dropColumn([
                'age',
                'qualification',
                'guardian_phone',
                'national_id',
                'address',
                'notes',
            ]);
        });
    }
};
