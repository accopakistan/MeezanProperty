<?php

namespace App\Providers;

use App\Jobs\RecalculateInvestmentIndex;
use App\Models\MarketObservation;
use App\Models\Project;
use App\Services\InvestmentIndex\Collection\Registry;
use App\Services\InvestmentIndex\IndexEngine;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Investment Index to the data it depends on: a change that can
 * alter a score queues an automatic recalculation. Evidence and legal
 * records queue it themselves (HasEvidenceProvenance).
 */
class InvestmentIndexServiceProvider extends ServiceProvider
{
    /** Project attributes the index reads. */
    protected const PROJECT_FIELDS = ['category', 'is_published', 'is_demo', 'developer', 'name'];

    public function boot(): void
    {
        Project::saved(function (Project $p) {
            if ($p->is_demo && ! $p->wasChanged('is_demo')) {
                return;
            }
            $inScope = isset(IndexEngine::categoryMap()[$p->category]) || isset(IndexEngine::categoryMap()[$p->getOriginal('category')]);
            if ($inScope && ($p->wasRecentlyCreated ? $p->is_published : $p->wasChanged(self::PROJECT_FIELDS))) {
                RecalculateInvestmentIndex::schedule('project');
            }
            // A new or renamed real project is matched against official list entries already collected.
            if (! $p->is_demo && ($p->wasRecentlyCreated || $p->wasChanged(['name', 'city', 'category', 'is_demo']))) {
                app(Registry::class)->matchProject($p);
            }
        });

        // Verified prices paid / valued and agreed rents of real projects feed price history and yield.
        MarketObservation::saved(function (MarketObservation $o) {
            $touchesVerified = $o->status === 'verified' || ($o->wasChanged('status') && $o->getOriginal('status') === 'verified');
            if ($o->project_id && ! $o->is_demo && $touchesVerified) {
                RecalculateInvestmentIndex::schedule('market_data');
            }
        });
    }
}
