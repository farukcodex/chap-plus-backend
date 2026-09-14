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
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('address')->nullable()->after('name');
            $table->string('city')->nullable()->after('address');
            $table->decimal('lat', 10, 7)->nullable()->after('facilities');
            $table->decimal('lon', 10, 7)->nullable()->after('lat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['address', 'city', 'lat', 'lon']);
        });
    }
};
