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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('merchant_commission_rate', 5, 2)->nullable()->after('delivery_fee');
            $table->decimal('admin_commission', 10, 2)->default(0.00)->after('merchant_commission_rate');
            $table->decimal('merchant_earnings', 10, 2)->default(0.00)->after('admin_commission');
            $table->decimal('rider_commission_rate', 5, 2)->default(0.00)->after('merchant_earnings');
            $table->decimal('rider_earnings', 10, 2)->default(0.00)->after('rider_commission_rate');
            $table->timestamp('commission_settled_at')->nullable()->after('rider_earnings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'merchant_commission_rate',
                'admin_commission',
                'merchant_earnings',
                'rider_commission_rate',
                'rider_earnings',
                'commission_settled_at',
            ]);
        });
    }
};
