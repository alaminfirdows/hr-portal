<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(15);
            $table->unsignedSmallInteger('half_day_minutes')->default(240);
            $table->unsignedSmallInteger('overtime_after_minutes')->default(30);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('status', 20);
            $table->boolean('is_late')->default(false);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('source', 20)->default('web');
            $table->string('ip_address', 45)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('shifts');
    }
};
