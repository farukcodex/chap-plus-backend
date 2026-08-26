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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            $table->string('sku')->nullable(); // Can be null if merchant doesn't use SKUs
            
            // JSON column for dynamic attributes: e.g. {"Size": "L", "Color": "Red"} or {"RAM": "16GB"}
            $table->json('attributes')->nullable(); 
            
            // Decimal price adjustment if this variant costs more/less than base_price
            $table->decimal('price_adjustment', 10, 2)->default(0.00); 
            
            // Stock quantity as a decimal to support fractional grocery (e.g. 1.5 kg)
            $table->decimal('stock_quantity', 10, 2)->default(0.00);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
