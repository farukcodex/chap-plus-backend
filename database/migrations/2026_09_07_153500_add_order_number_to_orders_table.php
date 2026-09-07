<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number')->nullable()->unique()->after('id');
        });

        // Automatically backfill all existing orders with sequential format (#ORD-00001)
        DB::table('orders')->whereNull('order_number')->orderBy('id')->each(function ($order) {
            DB::table('orders')->where('id', $order->id)->update([
                'order_number' => '#ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT)
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });
    }
};
