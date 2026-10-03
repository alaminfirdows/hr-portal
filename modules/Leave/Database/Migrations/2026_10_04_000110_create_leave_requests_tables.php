<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('day_part', 20)->default('full');
            $table->decimal('days', 6, 2);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('steps');
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('leave_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step');
            $table->string('step_role', 20);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_approvals');
        Schema::dropIfExists('leave_requests');
    }
};
