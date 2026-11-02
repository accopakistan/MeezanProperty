<?php

namespace App\Services\Crm;

use App\Models\LeadAppointment;
use App\Models\LeadAssignment;
use App\Models\LeadFollowUp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CRM figures from recorded data only. Definitions (per dealer, for
 * assignments made in the period):
 *
 * - received: lead assignments made to the dealer;
 * - contacted: of those, reached "contacted" or later at least once (first_contact_at);
 * - qualified: reached "qualified" or later at least once (qualified_at);
 * - viewings: appointments of type viewing that were not cancelled;
 * - won / lost: assignments currently in that stage;
 * - follow-up completion: completed ÷ (completed + overdue pending), for
 *   follow-ups due in the period; cancelled ones are left out.
 *
 * A lead shared with three dealers counts once per dealer here; the
 * marketplace lead itself is never "won" because one dealer won it.
 * Rates are only shown from config('meezan.crm.min_leads_for_rates') leads.
 */
class CrmStats
{
    /** @return array<string, int> stage value => count, for the dealer's active assignments */
    public function stageCounts(int $dealerId): array
    {
        return LeadAssignment::where('dealer_id', $dealerId)->where('status', 'active')
            ->groupBy('pipeline_stage')->selectRaw('pipeline_stage, count(*) as total')->pluck('total', 'pipeline_stage')
            ->map(fn ($n) => (int) $n)->all();
    }

    /**
     * Performance for many dealers in a fixed number of queries.
     *
     * @param  Collection<int, int>|array<int>  $dealerIds
     * @return Collection<int, array<string, int|float|null>> dealer id => figures
     */
    public function performance(Collection|array $dealerIds, ?Carbon $from = null): Collection
    {
        $ids = collect($dealerIds)->values();
        $from ??= now()->subDays(30);

        $assignments = LeadAssignment::whereIn('dealer_id', $ids)->where('assigned_at', '>=', $from)
            ->groupBy('dealer_id')
            ->selectRaw('dealer_id, count(*) as received,
                sum(case when first_contact_at is not null then 1 else 0 end) as contacted,
                sum(case when qualified_at is not null then 1 else 0 end) as qualified,
                sum(case when pipeline_stage = \'won\' then 1 else 0 end) as won,
                sum(case when pipeline_stage = \'lost\' then 1 else 0 end) as lost,
                sum(case when status = \'active\' and stale_flagged_at is not null then 1 else 0 end) as stale')
            ->get()->keyBy('dealer_id');

        $viewings = LeadAppointment::whereIn('dealer_id', $ids)->where('type', 'viewing')->where('status', '!=', 'cancelled')
            ->where('starts_at', '>=', $from)->groupBy('dealer_id')->selectRaw('dealer_id, count(*) as total')->pluck('total', 'dealer_id');

        $followUps = LeadFollowUp::whereIn('dealer_id', $ids)->whereBetween('due_at', [$from, now()])
            ->groupBy('dealer_id')
            ->selectRaw('dealer_id, sum(case when status = \'completed\' then 1 else 0 end) as completed,
                sum(case when status = \'pending\' then 1 else 0 end) as overdue')
            ->get()->keyBy('dealer_id');

        $lastActivity = LeadAssignment::whereIn('dealer_id', $ids)->groupBy('dealer_id')->select('dealer_id', DB::raw('max(last_activity_at) as last'))->pluck('last', 'dealer_id');
        $minimum = (int) config('meezan.crm.min_leads_for_rates');

        return $ids->mapWithKeys(function ($id) use ($assignments, $viewings, $followUps, $lastActivity, $minimum) {
            $a = $assignments[$id] ?? null;
            $f = $followUps[$id] ?? null;
            $received = (int) ($a->received ?? 0);
            $completed = (int) ($f->completed ?? 0);
            $overdue = (int) ($f->overdue ?? 0);

            return [$id => [
                'received' => $received,
                'contacted' => (int) ($a->contacted ?? 0),
                'qualified' => (int) ($a->qualified ?? 0),
                'viewings' => (int) ($viewings[$id] ?? 0),
                'won' => (int) ($a->won ?? 0),
                'lost' => (int) ($a->lost ?? 0),
                'stale' => (int) ($a->stale ?? 0),
                'followups_completed' => $completed,
                'followups_overdue' => $overdue,
                // Null = not enough data to show a rate.
                'contact_rate' => $received >= $minimum ? round($a->contacted / $received * 100) : null,
                'followup_rate' => ($completed + $overdue) >= $minimum ? round($completed / ($completed + $overdue) * 100) : null,
                'last_activity' => $lastActivity[$id] ?? null,
            ]];
        });
    }
}
