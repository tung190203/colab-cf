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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // morning, afternoon
            $table->string('name');
            $table->string('start_time');
            $table->string('end_time');
            $table->string('color')->nullable();
            $table->timestamps();
        });

        // Seed initial data
        DB::table('shifts')->insert([
            ['key' => 'morning', 'name' => 'Ca sáng', 'start_time' => '06:00', 'end_time' => '14:00', 'color' => '#f59e0b', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'afternoon', 'name' => 'Ca chiều', 'start_time' => '14:00', 'end_time' => '22:00', 'color' => '#3b82f6', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
