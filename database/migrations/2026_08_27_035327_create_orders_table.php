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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('merchant_profile_id')->nullable(); 
            // In a real app, an order might have items from multiple merchants, 
            // but usually it's split. We'll link merchant_profile_id.
            
            $table->decimal('total_amount', 10, 2);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->string('status')->default('pending_payment'); // pending_payment, paid, processing, on_the_way, delivered, failed
            $table->text('delivery_address');
            $table->string('payment_method')->default('mpesa');
            
            // M-Pesa specific fields
            $table->string('mpesa_checkout_request_id')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            $table->string('customer_phone_number')->nullable();
            
            // Delivery
            $table->string('delivery_otp', 10)->nullable();
            
            // Tracking & Reviews
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->tinyInteger('rating')->nullable();
            $table->text('review_comment')->nullable();
            
            $table->timestamps();
            
            $table->foreign('merchant_profile_id')->references('id')->on('merchant_profiles')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
