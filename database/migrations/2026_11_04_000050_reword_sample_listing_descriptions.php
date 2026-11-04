<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sample listings say "Sample listing" rather than "Demo listing" to visitors.
 * Only the opening line DemoContentSeeder wrote is reworded, and only on
 * listings still flagged is_demo; any description a dealer has edited away
 * from that line is left as it is.
 */
return new class extends Migration
{
    private const OLD = 'Demo listing for local development — this is not real inventory.';

    private const NEW = 'Sample listing for platform preview — this is not real inventory.';

    public function up(): void
    {
        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD);
    }

    private function swap(string $from, string $to): void
    {
        DB::table('properties')->where('is_demo', true)->where('description', 'like', $from.'%')
            ->get(['id', 'description'])
            ->each(fn ($p) => DB::table('properties')->where('id', $p->id)
                ->update(['description' => $to.substr($p->description, strlen($from))]));
    }
};
