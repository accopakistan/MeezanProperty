<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace fields: covered area, completion status and the lister's
 * declared relationship to a property; dealer lead capacity; buyer
 * notification preferences. All columns are nullable or defaulted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedInteger('covered_area_sqft')->nullable()->after('area_sqft')->index();
            $table->string('completion_status', 20)->nullable()->after('furnishing')->index();
            $table->string('authorization_type', 20)->nullable()->after('completion_status');
            $table->timestamp('authorization_confirmed_at')->nullable()->after('authorization_type');
        });

        Schema::table('dealer_profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('lead_capacity')->nullable()->after('accepts_leads');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_in_app')->default(true)->after('last_login_at');
            $table->boolean('notify_email')->default(false)->after('notify_in_app');
            $table->boolean('notify_whatsapp')->default(false)->after('notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['covered_area_sqft']);
            $table->dropIndex(['completion_status']);
            $table->dropColumn(['covered_area_sqft', 'completion_status', 'authorization_type', 'authorization_confirmed_at']);
        });
        Schema::table('dealer_profiles', fn (Blueprint $table) => $table->dropColumn('lead_capacity'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['notify_in_app', 'notify_email', 'notify_whatsapp']));
    }
};
