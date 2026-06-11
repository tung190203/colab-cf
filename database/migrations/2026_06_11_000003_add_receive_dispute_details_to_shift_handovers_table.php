<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->integer('receive_cash_actual')->nullable()->after('cash_diff');
            $table->text('receive_cash_reason')->nullable()->after('receive_cash_actual');
            $table->json('receive_material_discrepancies')->nullable()->after('damaged_materials');
        });
    }

    public function down(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->dropColumn([
                'receive_cash_actual',
                'receive_cash_reason',
                'receive_material_discrepancies',
            ]);
        });
    }
};
