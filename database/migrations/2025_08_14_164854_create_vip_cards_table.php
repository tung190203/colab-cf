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
        Schema::create('vip_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_number')->unique()->comment('Unique identifier for the VIP card');
            $table->unsignedBigInteger('user_id')->nullable()->comment('ID of the user who owns the card');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('card_type')->nullable()->comment('Type of VIP card (e.g., Gold, Silver, Platinum)');
            $table->date('expiry_date')->nullable()->comment('Expiry date of the VIP card');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vip_cards');
    }
};
