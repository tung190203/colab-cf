<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_schedules', 'is_holiday')) {
                $table->boolean('is_holiday')->default(false)->after('is_ot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('staff_schedules', 'is_holiday')) {
                $table->dropColumn('is_holiday');
            }
        });
    }
};
