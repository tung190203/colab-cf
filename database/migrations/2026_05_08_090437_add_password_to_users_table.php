<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('member')->after('phone');
            }
            if (!Schema::hasColumn('users', 'note')) {
                $table->text('note')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'image')) {
                $table->string('image')->nullable()->after('note');
            }
            // salary dùng để tính base lương khi tạo payroll
            if (!Schema::hasColumn('users', 'base_salary')) {
                $table->unsignedBigInteger('base_salary')->default(0)->after('image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumnIfExists('base_salary');
        });
    }
};
