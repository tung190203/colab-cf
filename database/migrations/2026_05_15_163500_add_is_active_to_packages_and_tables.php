<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('duration_label');
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('total_seating');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
