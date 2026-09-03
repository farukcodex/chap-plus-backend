<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_profile_id')->constrained()->cascadeOnDelete();
            
            $table->date('check_in_date');
            $table->date('check_out_date');
            
            $table->integer('rooms_booked')->default(1);
            $table->decimal('total_price', 10, 2);
            
            $table->string('customer_phone_number');
            
            $table->enum('status', [
                'pending_payment',
                'paid',
                'checked_in',
                'checked_out',
                'cancelled',
                'failed'
            ])->default('pending_payment');
            
            $table->string('mpesa_checkout_request_id')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_bookings');
    }
};
