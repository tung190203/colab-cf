<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_extras', function (Blueprint $table) {
            $table->unsignedInteger('free_applied')->default(0)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_extras', function (Blueprint $table) {
            $table->dropColumn('free_applied');
        });
    }
};
