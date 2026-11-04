<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Public names read naturally: the sample dealer seeded by DemoContentSeeder
 * loses the "(Demo)" / "Demo" wording in its display names. Only that seeded
 * account is touched, and only while its names are still the seeded ones;
 * it stays a demo account (its listings keep is_demo). Investment demo
 * projects are renamed by `php artisan meezan:investment-demo seed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dealer = DB::table('users')->where('email', 'dealer@meezanproperty.test')->first();
        if (! $dealer) {
            return;
        }
        DB::table('users')->where('id', $dealer->id)->where('name', 'Imran Dealer (Demo)')->update(['name' => 'Imran Dealer']);
        DB::table('dealer_profiles')->where('user_id', $dealer->id)->where('agency_name', 'Demo Realty (Sample Data)')->update(['agency_name' => 'Sample Realty']);
    }

    public function down(): void
    {
        $dealer = DB::table('users')->where('email', 'dealer@meezanproperty.test')->first();
        if ($dealer) {
            DB::table('users')->where('id', $dealer->id)->where('name', 'Imran Dealer')->update(['name' => 'Imran Dealer (Demo)']);
            DB::table('dealer_profiles')->where('user_id', $dealer->id)->where('agency_name', 'Sample Realty')->update(['agency_name' => 'Demo Realty (Sample Data)']);
        }
    }
};
