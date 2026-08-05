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
        Schema::create('spot_check_logs', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('shift_type'); // sang | chieu
            $table->timestamp('shift_start')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('checker_id')->constrained('users')->onDelete('cascade');
            $table->string('checker_role')->nullable();
            $table->json('staff_ids'); // [employee_id] array
            $table->json('items_checked'); // [{item_id, result, note, photos}]
            $table->integer('total_checked');
            $table->integer('total_passed');
            $table->integer('score'); // 0-100
            $table->boolean('is_locked')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spot_check_logs');
    }
};
