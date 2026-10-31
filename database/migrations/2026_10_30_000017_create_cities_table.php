<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Launch cities (national expansion). Additive: a new table plus the seven
 * reference rows, inserted only when missing. Centre coordinates are left
 * empty here and filled from OpenStreetMap by `meezan:geocode-areas`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('slug', 80)->unique();
            $table->string('province', 60);
            $table->string('country', 60)->default('Pakistan');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('coordinates_source', 40)->nullable();
            $table->boolean('is_launch')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            'Lahore' => 'Punjab',
            'Karachi' => 'Sindh',
            'Islamabad' => 'Islamabad Capital Territory',
            'Multan' => 'Punjab',
            'Faisalabad' => 'Punjab',
            'Quetta' => 'Balochistan',
            'Peshawar' => 'Khyber Pakhtunkhwa',
        ] as $name => $province) {
            DB::table('cities')->insertOrIgnore([
                'name' => $name, 'slug' => strtolower($name), 'province' => $province, 'country' => 'Pakistan',
                'is_launch' => true, 'is_active' => true, 'sort_order' => DB::table('cities')->count() + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
