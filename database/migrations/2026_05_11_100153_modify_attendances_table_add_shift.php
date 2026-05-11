<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropUnique(['staff_id', 'date']);
            $table->string('shift')->nullable()->after('date');
            $table->unique(['staff_id', 'date', 'shift']);
            $table->foreign('staff_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropUnique(['staff_id', 'date', 'shift']);
            $table->dropColumn('shift');
            $table->unique(['staff_id', 'date']);
            $table->foreign('staff_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
