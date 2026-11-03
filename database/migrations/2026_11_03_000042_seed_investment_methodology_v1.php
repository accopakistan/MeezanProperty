<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Methodology version 1: the proposed initial weights and parameters from
 * config/meezan.php (investment.default_*), recorded once so every later
 * change becomes a new version instead of rewriting this one. Inserts one
 * row; no existing data is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('investment_methodologies')->where('version', 1)->exists()) {
            return;
        }

        DB::table('investment_methodologies')->insert([
            'version' => 1,
            'name' => 'Initial proposed methodology',
            'weights' => json_encode(config('meezan.investment.default_weights')),
            'parameters' => json_encode(config('meezan.investment.default_parameters')),
            'notes' => 'Proposed initial weights from the Phase 14 brief. Not permanent business rules: review and adjust in the Data Centre.',
            'status' => 'active',
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('investment_methodologies')->where('version', 1)->delete();
    }
};
