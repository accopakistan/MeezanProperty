<?php

namespace App\Services\Leads;

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\BuyerRequirement;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use App\Notifications\LeadAssigned;
use App\Services\AuditLogger;
use App\Support\LocationResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relevance-first, area-based lead distribution.
 *
 * - Inquiries about a listing go only to that listing's dealer.
 * - Buyer requirements are shared only with explicit buyer consent, and only
 *   with active, verified dealers who operate in the requested areas.
 * - Every lead has a unique dedupe key so retries never double-assign, and
 *   every assignment decision is written to the audit log.
 */
class LeadRouter
{
    public function routeInquiry(Inquiry $inquiry): Lead
    {
        return DB::transaction(function () use ($inquiry) {
            $lead = $this->firstOrCreateLead('inquiry:'.$inquiry->id, function (Lead $lead) use ($inquiry) {
                $property = $inquiry->property;
                $lead->inquiry_id = $inquiry->id;
                $lead->property_id = $property?->id;
                $lead->area_id = $property?->area_id;
                $lead->purpose = $property?->purpose;
                $lead->type = $property?->type;
                $lead->source = $inquiry->source;
                $lead->consent_to_share = true;
            });

            if ($inquiry->recipient_id && ! $lead->assignments()->exists()) {
                $this->assign($lead, $inquiry->recipient_id, 100, 'Owner of the enquired listing');
            } elseif (! $inquiry->recipient_id) {
                $lead->status = LeadStatus::Unrouted;
                $lead->save();
            }

            return $lead;
        });
    }

    public function routeRequirement(BuyerRequirement $requirement, bool $consentToShare): ?Lead
    {
        if (! $consentToShare) {
            return null;
        }

        return DB::transaction(function () use ($requirement) {
            $lead = $this->firstOrCreateLead('requirement:'.$requirement->id, function (Lead $lead) use ($requirement) {
                $lead->buyer_requirement_id = $requirement->id;
                $lead->area_id = $requirement->area_ids[0] ?? null;
                $lead->purpose = $requirement->purpose;
                $lead->type = $requirement->type;
                $lead->source = $requirement->source;
                $lead->consent_to_share = true;
            });

            if ($lead->assignments()->exists()) {
                return $lead;
            }

            $dealers = $this->eligibleDealers($requirement)
                ->take((int) config('meezan.leads.max_dealers_per_requirement', 3));

            if ($dealers->isEmpty()) {
                $lead->status = LeadStatus::Unrouted;
                $lead->save();
                AuditLogger::record('lead.unrouted', $lead, ['reason' => 'No eligible verified dealer for the requested areas']);

                return $lead;
            }

            foreach ($dealers as $candidate) {
                $assignment = $this->assign($lead, $candidate['dealer']->id, $candidate['score'], $candidate['reason']);
                // Queued until the transaction commits.
                $candidate['dealer']->notify(new LeadAssigned($assignment));
            }

            return $lead;
        });
    }

    /**
     * Ranked dealers eligible for a requirement.
     *
     * @return Collection<int, array{dealer: User, score: int, reason: string}>
     */
    public function eligibleDealers(BuyerRequirement $requirement): Collection
    {
        // A dealer covering "DHA Lahore" fits a request for "DHA Phase 5", and the reverse.
        $areaIds = LocationResolver::related(array_map('intval', $requirement->area_ids ?? []));
        $group = $requirement->type?->group();

        $dealers = User::query()
            ->where('role', Role::Dealer)
            ->where('status', 'active')
            ->whereHas('dealerProfile', function ($q) use ($requirement, $areaIds) {
                $q->where('verification_status', DealerStatus::Verified)
                    ->where('business_type', '!=', 'owner')
                    ->where('accepts_leads', true)
                    // Only dealers who serve the requirement's city (multi-city coverage,
                    // or the profile's home city for dealers who have not chosen any).
                    ->where(fn ($c) => $c->whereHas('cities', fn ($city) => $city->where('name', $requirement->city))
                        ->orWhere(fn ($home) => $home->whereDoesntHave('cities')->where('city', $requirement->city)))
                    ->when($areaIds, fn ($p) => $p->whereHas('areas', fn ($a) => $a->whereIn('areas.id', $areaIds)));
            })
            ->with('dealerProfile.areas:id')
            ->withCount(['properties as relevant_listings' => function ($q) use ($group, $requirement) {
                $q->published()->where('purpose', $requirement->purpose)->when($group, fn ($g) => $g->inGroup($group));
            }, 'leadAssignments as active_leads' => fn ($q) => $q->where('status', 'active')])
            ->get()
            // Respect each dealer's lead capacity, when one is set.
            ->filter(fn (User $d) => ! $d->dealerProfile->lead_capacity || $d->active_leads < $d->dealerProfile->lead_capacity);

        return $dealers
            ->map(function (User $dealer) use ($areaIds) {
                $overlap = $areaIds
                    ? $dealer->dealerProfile->areas->pluck('id')->intersect($areaIds)->count()
                    : 0;
                $score = min(60, $overlap * 30) + min(40, $dealer->relevant_listings * 10);

                return [
                    'dealer' => $dealer,
                    'score' => $score,
                    'reason' => sprintf('Operates in %d requested area(s); %d relevant published listing(s)', $overlap, $dealer->relevant_listings),
                ];
            })
            ->sortByDesc('score')
            ->values();
    }

    public function reassign(Lead $lead, User $dealer, User $staff, string $reason): LeadAssignment
    {
        return DB::transaction(function () use ($lead, $dealer, $staff, $reason) {
            $lead->activeAssignments()->update(['status' => 'reassigned', 'ended_at' => now()]);

            $assignment = LeadAssignment::where('lead_id', $lead->id)->where('dealer_id', $dealer->id)->first() ?? new LeadAssignment;
            $assignment->lead_id = $lead->id;
            $assignment->dealer_id = $dealer->id;
            $assignment->fill(['score' => null, 'reason' => $reason]);
            $assignment->assigned_by = $staff->id;
            $assignment->status = 'active';
            $assignment->assigned_at = now();
            $assignment->ended_at = null;
            $assignment->save();

            $lead->status = LeadStatus::Assigned;
            $lead->save();

            AuditLogger::record('lead.reassigned', $lead, ['dealer_id' => $dealer->id, 'reason' => $reason], $staff->id);
            $dealer->notify(new LeadAssigned($assignment));

            return $assignment;
        });
    }

    protected function firstOrCreateLead(string $key, callable $fill): Lead
    {
        $existing = Lead::where('dedupe_key', $key)->lockForUpdate()->first();

        if ($existing) {
            return $existing;
        }

        $lead = new Lead(['dedupe_key' => $key]);
        $fill($lead);
        $lead->status = LeadStatus::New;
        $lead->save();

        return $lead;
    }

    protected function assign(Lead $lead, int $dealerId, ?int $score, string $reason): LeadAssignment
    {
        $assignment = new LeadAssignment(['score' => $score, 'reason' => $reason]);
        $assignment->lead_id = $lead->id;
        $assignment->dealer_id = $dealerId;
        $assignment->assigned_at = now();
        $assignment->save();

        $lead->status = LeadStatus::Assigned;
        $lead->save();

        AuditLogger::record('lead.assigned', $lead, ['dealer_id' => $dealerId, 'score' => $score, 'reason' => $reason], null);

        return $assignment;
    }
}
