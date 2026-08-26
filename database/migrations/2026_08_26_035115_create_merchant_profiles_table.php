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
        Schema::create('merchant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->string('country');
            $table->string('city');
            $table->string('currency', 3)->nullable(); // e.g., 'USD', 'CAD', 'BDT'
            
            $table->string('business_name')->nullable(); 
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            
            $table->string('profile_image_path')->nullable();
            $table->string('cover_image_path')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_profiles');
    }
};
