<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('shift_type', 30);
            $table->foreignId('outgoing_employee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('incoming_employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handover_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->integer('cash_theoretical')->default(0);
            $table->integer('cash_actual')->default(0);
            $table->integer('cash_diff')->default(0);
            $table->text('cash_note')->nullable();
            $table->integer('total_orders')->default(0);
            $table->integer('revenue_cash')->default(0);
            $table->integer('revenue_transfer')->default(0);
            $table->integer('total_revenue')->default(0);
            $table->json('nvl_snapshot');
            $table->json('equipment_checklist')->nullable();
            $table->text('handover_note')->nullable();
            $table->boolean('has_alert')->default(false);
            $table->string('status', 30)->default('pending');
            $table->text('dispute_note')->nullable();
            $table->timestamps();

            $table->index(['date', 'shift_type']);
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
    }
};
