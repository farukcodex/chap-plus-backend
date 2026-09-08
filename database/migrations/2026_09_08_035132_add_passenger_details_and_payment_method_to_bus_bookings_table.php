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
        Schema::table('bus_bookings', function (Blueprint $table) {
            $table->string('passenger_name')->nullable()->after('seat_numbers');
            $table->string('passenger_phone')->nullable()->after('passenger_name');
            $table->string('passenger_email')->nullable()->after('passenger_phone');
            $table->string('payment_method')->default('mpesa')->after('total_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bus_bookings', function (Blueprint $table) {
            $table->dropColumn(['passenger_name', 'passenger_phone', 'passenger_email', 'payment_method']);
        });
    }
};
