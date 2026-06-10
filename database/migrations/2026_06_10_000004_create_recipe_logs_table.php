<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('extras')->nullOnDelete();
            $table->string('product_name');
            $table->json('ingredients_before')->nullable();
            $table->json('ingredients_after');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['recipe_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_logs');
    }
};
