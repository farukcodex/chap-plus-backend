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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Step 1: Sign up fields
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('currency')->nullable();
            
            // Step 3: Profile setup fields
            $table->string('phone_number')->nullable();
            $table->string('gender')->nullable(); // Male, Female, Other
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
