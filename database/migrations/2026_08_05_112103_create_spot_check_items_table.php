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
        Schema::create('spot_check_items', function (Blueprint $table) {
            $table->id();
            $table->string('group'); // Vệ sinh | Không gian | Setup | Tác phong | Phục vụ
            $table->string('title');
            $table->integer('order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spot_check_items');
    }
};
