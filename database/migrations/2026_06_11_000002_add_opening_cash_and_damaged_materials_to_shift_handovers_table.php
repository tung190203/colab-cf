<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->integer('opening_cash')->default(0)->after('received_at');
            $table->json('damaged_materials')->nullable()->after('nvl_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->dropColumn(['opening_cash', 'damaged_materials']);
        });
    }
};
