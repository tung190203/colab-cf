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
        if (!Schema::hasTable('floor_layouts')) {
            Schema::create('floor_layouts', function (Blueprint $table) {
                $table->id();
                $table->integer('floor')->default(2)->unique();
                $table->string('name')->default('Tầng 2');
                $table->json('layout_json')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('tables', function (Blueprint $table) {
            if (!Schema::hasColumn('tables', 'floor')) {
                $table->integer('floor')->default(1)->after('code');
            }
            if (!Schema::hasColumn('tables', 'seat_price')) {
                $table->integer('seat_price')->default(50000)->after('total_seating');
            }
            if (!Schema::hasColumn('tables', 'block_price')) {
                $table->integer('block_price')->default(300000)->after('seat_price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            if (Schema::hasColumn('tables', 'floor')) {
                $table->dropColumn('floor');
            }
            if (Schema::hasColumn('tables', 'seat_price')) {
                $table->dropColumn('seat_price');
            }
            if (Schema::hasColumn('tables', 'block_price')) {
                $table->dropColumn('block_price');
            }
        });

        Schema::dropIfExists('floor_layouts');
    }
};
