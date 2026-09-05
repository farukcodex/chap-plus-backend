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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Polymorphic relation to handle HotelBookings, Orders, etc.
            $table->string('refundable_type');
            $table->unsignedBigInteger('refundable_id');
            
            $table->decimal('amount', 10, 2);
            
            // Refund state tracking
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            
            // M-Pesa B2C specific fields
            $table->string('mpesa_conversation_id')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            
            // Admin tracking
            $table->foreignId('processed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['refundable_type', 'refundable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
