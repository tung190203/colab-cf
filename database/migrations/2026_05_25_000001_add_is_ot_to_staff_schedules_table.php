<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_schedules', 'is_ot')) {
                $table->boolean('is_ot')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('staff_schedules', 'is_ot')) {
                $table->dropColumn('is_ot');
            }
        });
    }
};
