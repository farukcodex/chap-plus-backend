<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_per_seat', 10, 2);
            $table->string('bus_type'); // AC, Non-AC
            $table->string('driver_position'); // RHD, LHD
            $table->string('seat_pattern'); // 2-2, 1-2, etc
            $table->integer('total_rows');
            $table->integer('back_row_seats');
            $table->integer('total_bookable_seats');
            $table->boolean('has_middle_door')->default(false);
            
            $table->string('departure_place');
            $table->string('departure_time');
            $table->string('rest_place')->nullable();
            $table->string('rest_duration')->nullable();
            $table->string('destination_place');
            $table->string('destination_time');
            
            $table->json('facilities')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
