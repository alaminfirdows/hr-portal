<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money is integer minor units; percentages are basis points (50% = 5000).
        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('type', 20);
            $table->string('calculation', 30);
            $table->unsignedBigInteger('value')->nullable();
            $table->text('formula')->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('applies_to_all')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('employee_pay_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'pay_component_id']);
        });

        Schema::create('tax_slabs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('min_amount');
            $table->unsignedBigInteger('max_amount')->nullable();
            $table->unsignedInteger('rate_bp');
            $table->timestamps();
        });

        Schema::create('compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('basic_salary');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();
            $table->string('status', 20);
            $table->unsignedInteger('employee_count')->default(0);
            $table->unsignedBigInteger('total_gross')->default(0);
            $table->unsignedBigInteger('total_deductions')->default(0);
            $table->unsignedBigInteger('total_net')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7)->index();
            $table->string('type', 20);
            $table->string('name');
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('basic');
            $table->unsignedBigInteger('gross');
            $table->unsignedBigInteger('total_deductions');
            $table->unsignedBigInteger('net');
            $table->boolean('net_clamped')->default(false);
            $table->unsignedSmallInteger('working_days');
            $table->decimal('paid_days', 5, 2);
            $table->decimal('unpaid_days', 5, 2)->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('type', 20);
            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('sort')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('compensations');
        Schema::dropIfExists('tax_slabs');
        Schema::dropIfExists('employee_pay_components');
        Schema::dropIfExists('pay_components');
    }
};
