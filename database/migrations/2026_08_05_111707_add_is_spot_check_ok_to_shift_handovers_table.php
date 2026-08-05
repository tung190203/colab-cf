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
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->boolean('incoming_spot_check_ok')->default(false)->after('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shift_handovers', function (Blueprint $table) {
            $table->dropColumn('incoming_spot_check_ok');
        });
    }
};
