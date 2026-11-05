<?php

use App\Models\MarketSource;
use App\Services\AuditLogger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner's decision, 5 November 2026: official lists copied by hand wait for a
 * person, like the scanned Multan list. Records verified automatically from
 * these lists go back to review. Nothing is deleted; superseded and rejected
 * records stay as they are, as does anything a member of staff verified.
 * Lists read directly from an authority's own data are not touched.
 */
return new class extends Migration
{
    protected const SOURCES = [
        'QDA — approved private housing schemes',
        'FDA — approved private housing schemes',
        'FDA — schemes developed by FDA',
        'MDA — commercial schemes developed by MDA',
        'PDA — approved housing societies',
        'PDA — un-approved housing societies',
        'SBCA — NOC for sale and advertisement (public sale projects)',
    ];

    public function up(): void
    {
        foreach (MarketSource::whereIn('name', self::SOURCES)->where('is_demo', false)->get() as $source) {
            $moved = DB::table('investment_legal_records')->where('source_id', $source->id)->where('verification_status', 'verified')
                ->whereNull('verified_by')->where('verification_method', 'official_registry')
                ->update(['verification_status' => 'unverified', 'verification_method' => 'awaiting_staff_review', 'verified_at' => null, 'updated_at' => now()]);
            if ($moved > 0) {
                AuditLogger::record('investment_index.records_moved_to_review', $source, ['records' => $moved, 'reason' => 'Hand-read official list; owner decision, 5 Nov 2026']);
            }
        }
    }

    public function down(): void
    {
        // Verified status is not restored automatically: a person decides.
    }
};
