<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete old records to avoid constraint violations when modifying unique index
        DB::table('daily_inventory_items')->delete();
        DB::table('daily_inventories')->delete();

        Schema::table('daily_inventories', function (Blueprint $table) {
            $table->dropUnique(['date']); // Assuming the default name is table_date_unique
            $table->string('shift_type')->default('Ca 1')->after('date');
            $table->unique(['date', 'shift_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_inventories', function (Blueprint $table) {
            $table->dropUnique(['date', 'shift_type']);
            $table->dropColumn('shift_type');
            $table->unique('date');
        });
    }
};
