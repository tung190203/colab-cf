<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'is_manual_adjusted')) {
                $table->boolean('is_manual_adjusted')->default(false)->after('note');
            }
            if (!Schema::hasColumn('attendances', 'adjusted_by')) {
                $table->foreignId('adjusted_by')->nullable()->after('is_manual_adjusted')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('attendances', 'adjusted_at')) {
                $table->timestamp('adjusted_at')->nullable()->after('adjusted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'adjusted_by')) {
                $table->dropConstrainedForeignId('adjusted_by');
            }
            if (Schema::hasColumn('attendances', 'is_manual_adjusted')) {
                $table->dropColumn('is_manual_adjusted');
            }
            if (Schema::hasColumn('attendances', 'adjusted_at')) {
                $table->dropColumn('adjusted_at');
            }
        });
    }
};
