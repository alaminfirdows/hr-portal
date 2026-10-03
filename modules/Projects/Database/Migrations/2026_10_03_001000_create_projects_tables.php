<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->string('visibility', 20)->default('public')->index();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('client')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->unsignedBigInteger('budget')->nullable(); // minor units
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->unsignedSmallInteger('allocation_percent')->default(100);
            $table->date('joined_on');
            $table->timestamps();
            $table->unique(['project_id', 'employee_id']);
            $table->index('employee_id');
        });

        Schema::create('project_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label', 100);
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('project_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_definition_id')->constrained('project_field_definitions')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'field_definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_field_values');
        Schema::dropIfExists('project_field_definitions');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
