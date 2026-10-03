<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->string('color', 20)->default('blue');
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('allow_half_day')->default(false);
            $table->string('accrual_method', 20)->default('yearly');
            $table->decimal('annual_quota', 6, 2)->nullable();
            $table->decimal('carry_forward_cap', 6, 2)->default(0);
            $table->boolean('prorate_new_joiners')->default(true);
            $table->json('approval_steps');
            $table->unsignedSmallInteger('max_consecutive_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled', 6, 2)->default(0);
            $table->decimal('carried_forward', 6, 2)->default(0);
            $table->decimal('adjustment', 6, 2)->default(0);
            $table->decimal('used', 6, 2)->default(0);
            $table->decimal('pending', 6, 2)->default(0);
            $table->string('last_accrued_month', 7)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
