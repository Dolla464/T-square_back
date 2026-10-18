<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_attendance_card_bulk_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_operation_id')->unique();
            $table->unsignedBigInteger('learning_group_id');
            $table->unsignedBigInteger('initiated_by_user_id');
            $table->string('mode', 32);
            $table->boolean('confirm_reissue')->default(false);
            $table->string('revocation_reason', 500)->nullable();
            $table->char('request_fingerprint', 64);
            $table->unsignedInteger('snapshot_count')->default(0);
            $table->string('status', 24)->default('open');
            $table->unsignedInteger('next_batch_index')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamps();

            $table->index(['learning_group_id', 'status'], 'gac_bulk_ops_group_status_idx');
            $table->index('created_at', 'gac_bulk_ops_created_idx');

            $table->foreign('learning_group_id', 'gac_bulk_ops_group_fk')
                ->references('id')->on('learning_groups')->restrictOnDelete();
            $table->foreign('initiated_by_user_id', 'gac_bulk_ops_user_fk')
                ->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('group_attendance_card_bulk_operation_students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_attendance_card_bulk_operation_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedInteger('sequence');
            $table->string('planned_action', 16);
            $table->string('status', 16)->default('pending');
            $table->string('result_code', 64)->nullable();
            $table->unsignedBigInteger('card_id')->nullable();
            $table->timestamp('token_response_generated_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['group_attendance_card_bulk_operation_id', 'student_id'], 'gac_bulk_op_students_op_student_unique');
            $table->unique(['group_attendance_card_bulk_operation_id', 'sequence'], 'gac_bulk_op_students_op_sequence_unique');
            $table->index(['group_attendance_card_bulk_operation_id', 'status'], 'gac_bulk_op_students_op_status_idx');

            $table->foreign('group_attendance_card_bulk_operation_id', 'gac_bulk_stu_op_fk')
                ->references('id')->on('group_attendance_card_bulk_operations')->cascadeOnDelete();
            $table->foreign('student_id', 'gac_bulk_stu_student_fk')
                ->references('id')->on('students')->restrictOnDelete();
            $table->foreign('card_id', 'gac_bulk_stu_card_fk')
                ->references('id')->on('student_attendance_cards')->nullOnDelete();
        });

        Schema::table('student_attendance_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('group_attendance_card_bulk_operation_id')
                ->nullable()
                ->after('issued_by_user_id');
            $table->foreign('group_attendance_card_bulk_operation_id', 'gac_cards_bulk_op_fk')
                ->references('id')->on('group_attendance_card_bulk_operations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_attendance_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_attendance_card_bulk_operation_id');
        });

        Schema::dropIfExists('group_attendance_card_bulk_operation_students');
        Schema::dropIfExists('group_attendance_card_bulk_operations');
    }
};
