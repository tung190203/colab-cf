<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_adjustment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('adjusted_by')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('shift');
            $table->timestamp('old_check_in_at')->nullable();
            $table->timestamp('old_check_out_at')->nullable();
            $table->timestamp('new_check_in_at')->nullable();
            $table->timestamp('new_check_out_at')->nullable();
            $table->text('old_note')->nullable();
            $table->text('new_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_adjustment_logs');
    }
};
