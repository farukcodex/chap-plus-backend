<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE hotel_bookings MODIFY COLUMN status ENUM('pending_payment', 'paid', 'confirmed', 'failed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending_payment'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE hotel_bookings MODIFY COLUMN status ENUM('pending_payment', 'paid', 'failed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending_payment'");
    }
};
