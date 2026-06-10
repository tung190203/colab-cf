<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('extras')->nullOnDelete();
            $table->string('product_name');
            $table->json('ingredients');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('product_id');
            $table->index(['active', 'product_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
