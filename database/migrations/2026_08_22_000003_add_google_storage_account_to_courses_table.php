<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('google_storage_account_id')
                ->nullable()
                ->after('google_drive_link')
                ->constrained('google_storage_accounts')
                ->nullOnDelete();

            $table->string('google_drive_folder_id', 255)
                ->nullable()
                ->after('google_storage_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('google_storage_account_id');
            $table->dropColumn('google_drive_folder_id');
        });
    }
};
