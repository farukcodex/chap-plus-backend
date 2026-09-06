<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bus_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained()->cascadeOnDelete();
            $table->date('travel_date');
            $table->json('seat_numbers');
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['pending_payment', 'paid', 'cancelled', 'failed'])->default('pending_payment');
            $table->string('mpesa_receipt_number')->nullable();
            $table->string('mpesa_checkout_request_id')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_bookings');
    }
};
