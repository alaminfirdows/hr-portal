<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('employee_code', 30)->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('personal_email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('photo_path')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('joined_on');
            $table->date('exited_on')->nullable();
            $table->string('status', 20)->default('invited')->index();
            $table->string('employment_type', 20)->default('full_time');
            $table->string('work_type', 20)->default('onsite');
            $table->text('address')->nullable();
            $table->json('emergency_contact')->nullable();
            $table->json('skills')->nullable();
            $table->json('links')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('field', 50);
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('reason')->nullable();
            $table->date('effective_on');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_histories');
        Schema::dropIfExists('employees');
    }
};
