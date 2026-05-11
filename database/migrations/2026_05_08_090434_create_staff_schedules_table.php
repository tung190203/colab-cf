<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->enum('shift', ['morning', 'afternoon'])->default('morning');
            $table->enum('status', ['scheduled', 'completed', 'absent'])->default('scheduled');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'date', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_schedules');
    }
};
