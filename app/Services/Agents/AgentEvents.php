<?php

namespace App\Services\Agents;

use App\Enums\PropertyStatus;
use App\Models\DealerProfile;
use App\Models\InvestmentCalculation;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Business events that start agent work (when MEEZAN_AGENT_EVENTS is on):
 * a listing submitted for review, a dealer registering, a new lead, new
 * investment rankings published. Subscriptions nearing expiry and system
 * errors are picked up by the scheduled jobs (meezan:agents). Each
 * queues one read-only task after the database commit, with an idempotency
 * key so a repeated event never creates a second task. Agents only report
 * here; nothing is changed on the record.
 */
class AgentEvents
{
    public static function register(): void
    {
        if (! config('meezan.agents.events')) {
            return;
        }

        Property::saved(function (Property $p) {
            if ($p->status === PropertyStatus::Pending && ($p->wasRecentlyCreated || $p->wasChanged('status'))) {
                static::queue('property-manager', 'review', "Review new listing #{$p->id} submitted for moderation", ['property_id' => $p->id], "event-property-submitted-{$p->id}-".$p->updated_at?->timestamp);
            }
        });
        DealerProfile::created(fn (DealerProfile $d) => static::queue('dealer-manager', 'new_dealer', "Review new dealer registered (profile #{$d->id})", ['dealer_profile_id' => $d->id, 'dealer_id' => $d->user_id], "event-dealer-registered-{$d->id}"));
        InvestmentCalculation::saved(function (InvestmentCalculation $c) {
            if ($c->status === 'published' && $c->wasChanged('status')) {
                static::queue('investment-analyst', 'data_status', "Investment data updated: rankings #{$c->id} published", ['calculation_id' => $c->id], "event-investment-published-{$c->id}");
            }
        });
        Lead::created(fn (Lead $l) => static::queue('sales-manager', 'new_lead', "Prioritise new lead #{$l->id}", ['lead_id' => $l->id], "event-lead-created-{$l->id}"));
    }

    protected static function queue(string $agent, string $intent, string $request, array $input, string $key): void
    {
        DB::afterCommit(function () use ($agent, $intent, $request, $input, $key) {
            try {
                app(CentralAiOrchestrator::class)->submit($request, $agent, null, 'event', $input, $key, queue: true, intent: $intent);
            } catch (Throwable $e) {
                report($e); // an agent problem never blocks the business action
            }
        });
    }
}
