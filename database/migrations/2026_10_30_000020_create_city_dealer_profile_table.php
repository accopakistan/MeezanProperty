<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cities a dealer serves (multi-city coverage). Existing dealers are given
 * only the city already on their profile; nothing is widened automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_dealer_profile', function (Blueprint $table) {
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dealer_profile_id')->constrained()->cascadeOnDelete();
            $table->primary(['city_id', 'dealer_profile_id']);
            $table->index('dealer_profile_id');
        });

        $cities = DB::table('cities')->pluck('id', 'name');
        foreach (DB::table('dealer_profiles')->get(['id', 'city']) as $dealer) {
            if (isset($cities[$dealer->city])) {
                DB::table('city_dealer_profile')->insertOrIgnore(['city_id' => $cities[$dealer->city], 'dealer_profile_id' => $dealer->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('city_dealer_profile');
    }
};
