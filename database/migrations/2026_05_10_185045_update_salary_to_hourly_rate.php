<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'base_salary') && !Schema::hasColumn('users', 'hourly_rate')) {
                $table->renameColumn('base_salary', 'hourly_rate');
            } elseif (Schema::hasColumn('users', 'base_salary') && Schema::hasColumn('users', 'hourly_rate')) {
                $table->dropColumn('base_salary');
            }
        });

        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'base_salary') && !Schema::hasColumn('payrolls', 'calculated_salary')) {
                $table->renameColumn('base_salary', 'calculated_salary');
            }
            if (!Schema::hasColumn('payrolls', 'hourly_rate')) {
                $table->unsignedBigInteger('hourly_rate')->default(0)->after('year');
            }
            if (!Schema::hasColumn('payrolls', 'worked_hours')) {
                $table->decimal('worked_hours', 10, 2)->default(0)->after('hourly_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate', 'worked_hours']);
            if (Schema::hasColumn('payrolls', 'calculated_salary')) {
                $table->renameColumn('calculated_salary', 'base_salary');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'hourly_rate') && !Schema::hasColumn('users', 'base_salary')) {
                $table->renameColumn('hourly_rate', 'base_salary');
            }
        });
    }
};
