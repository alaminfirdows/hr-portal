<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compensations', function (Blueprint $table) {
            $table->unique(['employee_id', 'effective_from'], 'compensations_employee_effective_from_unique');
        });
    }

    public function down(): void
    {
        Schema::table('compensations', function (Blueprint $table) {
            $table->dropUnique('compensations_employee_effective_from_unique');
        });
    }
};
