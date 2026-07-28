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
        Schema::create('daily_inventories', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('draft'); // draft, confirmed
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('daily_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daily_inventory_id');
            $table->unsignedBigInteger('material_id');
            $table->decimal('opening_stock', 10, 3)->default(0);
            $table->decimal('imported_stock', 10, 3)->default(0);
            $table->decimal('used_stock', 10, 3)->default(0);
            $table->decimal('closing_stock', 10, 3)->default(0);
            $table->timestamps();

            $table->foreign('daily_inventory_id')->references('id')->on('daily_inventories')->onDelete('cascade');
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
            
            $table->unique(['daily_inventory_id', 'material_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_inventory_items');
        Schema::dropIfExists('daily_inventories');
    }
};
