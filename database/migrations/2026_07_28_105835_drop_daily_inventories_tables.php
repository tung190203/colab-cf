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
        Schema::dropIfExists('daily_inventory_items');
        Schema::dropIfExists('daily_inventories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not recreating them, as this is a permanent removal to merge into ShiftHandover
    }
};
