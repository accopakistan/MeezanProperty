<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Takes sample and test content off the public site without deleting it.
 *
 * - Adds dealer_profiles.is_demo: a sample or test dealer is never listed in
 *   the public directory and never shows a "Verified" badge. Its verification
 *   record is kept as it was.
 * - Archives published sample listings (is_demo) and published listings whose
 *   description is placeholder text. Archived listings stay in admin.
 *
 * Every condition is specific, so on a database without these records this
 * changes nothing. Each change is written to the audit log.
 */
return new class extends Migration
{
    /** Dealers the owner confirmed as test accounts in the 6 Nov 2026 QA review. */
    protected const CONFIRMED_TEST_DEALERS = ['jonathon-estate'];

    public function up(): void
    {
        if (! Schema::hasColumn('dealer_profiles', 'is_demo')) {
            Schema::table('dealer_profiles', function (Blueprint $table) {
                $table->boolean('is_demo')->default(false)->index();
            });
        }

        $now = now();
        $audit = function (string $action, string $type, int $id, array $properties) use ($now) {
            DB::table('audit_logs')->insert(['user_id' => null, 'action' => $action, 'auditable_type' => $type, 'auditable_id' => $id,
                'properties' => json_encode($properties + ['by' => 'migration 2026_11_06_000060']), 'created_at' => $now]);
        };

        $sampleDealers = DB::table('dealer_profiles')->join('users', 'users.id', '=', 'dealer_profiles.user_id')
            ->where('dealer_profiles.is_demo', false)
            ->where(fn ($q) => $q->where('dealer_profiles.bio', 'like', 'Sample dealer profile%')
                ->orWhere('users.email', 'like', '%@meezanproperty.test')
                ->orWhereIn('dealer_profiles.slug', self::CONFIRMED_TEST_DEALERS))
            ->pluck('dealer_profiles.id');
        foreach ($sampleDealers as $id) {
            DB::table('dealer_profiles')->where('id', $id)->update(['is_demo' => true, 'updated_at' => $now]);
            $audit('dealer.marked_sample', 'App\\Models\\DealerProfile', $id, ['reason' => 'Sample or test dealer: hidden from the public directory, badge suppressed, verification record kept']);
        }

        $placeholder = fn ($q) => $q->where('description', 'like', '%lorem ipsum%')
            ->orWhere('description', 'like', '%pseudo-Latin placeholder text%');
        $listings = DB::table('properties')->where('status', 'published')->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('is_demo', true)->orWhere($placeholder))
            ->get(['id', 'is_demo']);
        foreach ($listings as $listing) {
            DB::table('properties')->where('id', $listing->id)->update(['status' => 'archived', 'updated_at' => $now]);
            $audit('property.archived', 'App\\Models\\Property', $listing->id, ['from' => 'published', 'reason' => $listing->is_demo ? 'Sample listing' : 'Test listing with placeholder text']);
        }
    }

    public function down(): void
    {
        // Nothing is reversed automatically: an admin can republish a listing or clear a dealer's flag.
    }
};
