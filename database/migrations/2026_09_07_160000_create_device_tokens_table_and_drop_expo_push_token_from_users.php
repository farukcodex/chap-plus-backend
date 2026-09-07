<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform', 20)->nullable();     // android, ios, web
            $table->string('device_name', 100)->nullable(); // e.g. iPhone 15 Pro, Pixel 8
            $table->string('device_id', 255)->nullable();   // Hardware or installation UUID
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // Safely migrate existing expo_push_token records if any exist
        if (Schema::hasColumn('users', 'expo_push_token')) {
            $existingUsers = DB::table('users')
                ->whereNotNull('expo_push_token')
                ->where('expo_push_token', '!=', '')
                ->get();

            foreach ($existingUsers as $user) {
                DB::table('device_tokens')->insertOrIgnore([
                    'user_id'      => $user->id,
                    'token'        => $user->expo_push_token,
                    'platform'     => null,
                    'device_name'  => null,
                    'device_id'    => null,
                    'last_used_at' => now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            // Drop legacy expo_push_token column from users table
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('expo_push_token');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('users', 'expo_push_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('expo_push_token')->nullable()->after('email');
            });
        }

        Schema::dropIfExists('device_tokens');
    }
};
