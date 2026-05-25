<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penalty_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('penalty_rules', 'type')) {
                $table->string('type', 20)->default('penalty')->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('penalty_rules', function (Blueprint $table) {
            if (Schema::hasColumn('penalty_rules', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
