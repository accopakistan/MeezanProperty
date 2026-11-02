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
use App\Notifications\BillingAdminAlert;
use App\Notifications\CrmReminder;
use App\Notifications\LeadAssigned;
use App\Services\AuditLogger;
use App\Services\Billing\Entitlements;
use App\Services\Crm\DealerCrm;
use App\Support\LocationResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Relevance-first, area-based lead distribution.
 *
 * - Inquiries about a listing go only to that listing's dealer.
 * - Buyer requirements are shared only with explicit buyer consent, and only
 *   with active, verified dealers who operate in the requested areas.
 * - Each dealer's package limits how many requirement leads they receive a
 *   month. When every suitable dealer is at their limit the lead is held
 *   for staff (or, if configured, assigned over the limit), never dropped.
 * - Every lead has a unique dedupe key so retries never double-assign, and
 *   every assignment decision is written to the audit log.
 */
class LeadRouter
{
    public function __construct(protected Entitlements $entitlements) {}

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

            $eligible = $this->eligibleDealers($requirement);
            $max = (int) config('meezan.leads.max_dealers_per_requirement', 3);
            $dealers = $eligible->reject(fn ($c) => $c['at_capacity'])->take($max);

            if ($eligible->isEmpty()) {
                $lead->status = LeadStatus::Unrouted;
                $lead->save();
                AuditLogger::record('lead.unrouted', $lead, ['reason' => 'No eligible verified dealer for the requested areas']);

                return $lead;
            }

            // Suitable dealers who missed out because of their lead limit hear about it (once a day).
            $eligible->take($max)->filter(fn ($c) => $c['at_capacity'])
                ->each(fn ($c) => $this->entitlements->notifyLimitReached($c['dealer'], 'leads'));

            if ($dealers->isEmpty()) {
                if (config('meezan.billing.lead_overflow') !== 'allow_over_limit') {
                    $lead->status = LeadStatus::Unrouted;
                    $lead->hold_reason = 'capacity';
                    $lead->save();
                    AuditLogger::record('lead.held', $lead, ['reason' => 'Every eligible dealer has used their monthly lead allowance', 'eligible' => $eligible->count()]);
                    Notification::send(User::activeAdmins(), new BillingAdminAlert('leads_held', $lead, 'A buyer requirement matched '.$eligible->count().' dealer(s), all at their monthly lead limit. Assign it manually or it will be retried when allowances reset.'));

                    return $lead;
                }
                $dealers = $eligible->take($max)->map(fn ($c) => ['reason' => $c['reason'].'; assigned over the monthly lead limit'] + $c);
            }

            $lead->hold_reason = null;
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

        // Package limits and features for all candidates in two queries.
        $ids = $dealers->pluck('id');
        $plans = $this->entitlements->plans($ids);
        $used = $this->entitlements->leadUsage($ids);

        return $dealers
            ->map(function (User $dealer) use ($areaIds, $plans, $used) {
                $overlap = $areaIds
                    ? $dealer->dealerProfile->areas->pluck('id')->intersect($areaIds)->count()
                    : 0;
                $score = min(60, $overlap * 30) + min(40, $dealer->relevant_listings * 10);
                $plan = $plans[$dealer->id];
                $priority = $plan->hasFeature('priority_leads');
                $limit = $plan->limit('leads');

                return [
                    'dealer' => $dealer,
                    'score' => $score + ($priority ? (int) config('meezan.billing.priority_lead_boost') : 0),
                    'reason' => sprintf('Operates in %d requested area(s); %d relevant published listing(s)', $overlap, $dealer->relevant_listings).($priority ? '; priority package' : ''),
                    'at_capacity' => $limit !== null && ($used[$dealer->id] ?? 0) >= $limit,
                    'leads_used' => $used[$dealer->id] ?? 0,
                    'lead_limit' => $limit,
                ];
            })
            ->sortByDesc('score')
            ->values();
    }

    public function reassign(Lead $lead, User $dealer, User $staff, string $reason): LeadAssignment
    {
        return DB::transaction(function () use ($lead, $dealer, $staff, $reason) {
            // The dealers it is taken from keep nothing new; their CRM history stays on their assignment.
            $lead->activeAssignments()->where('dealer_id', '!=', $dealer->id)->with('dealer')->get()
                ->each(fn (LeadAssignment $old) => $this->endAssignment($old, 'reassigned', $staff, $reason));

            $assignment = $this->activate($lead, $dealer, $staff, $reason);

            $lead->status = LeadStatus::Assigned;
            $lead->hold_reason = null;
            $lead->save();

            AuditLogger::record('lead.reassigned', $lead, ['dealer_id' => $dealer->id, 'reason' => $reason], $staff->id);
            $dealer->notify(new LeadAssigned($assignment));

            return $assignment;
        });
    }

    /**
     * Problems that stop a dealer receiving this lead (empty = eligible):
     * verified and active, takes leads, covers the lead's city and area, and
     * has lead allowance left in their package.
     *
     * @return list<string>
     */
    public function eligibilityProblems(Lead $lead, User $dealer): array
    {
        $dealer->loadMissing('dealerProfile.cities:id,name', 'dealerProfile.areas:id');
        $profile = $dealer->dealerProfile;
        $problems = [];

        if (! $dealer->isVerifiedDealer() || ! $dealer->isActive()) {
            $problems[] = 'not an active verified dealer';
        }
        if (! $profile || $profile->business_type === 'owner' || ! $profile->accepts_leads) {
            $problems[] = 'not accepting leads';
        }
        $city = $lead->area?->city ?? $lead->requirement?->city ?? $lead->property?->city;
        $cities = $profile?->cities->pluck('name')->all() ?: [$profile?->city];
        if ($city && ! in_array($city, $cities, true)) {
            $problems[] = 'does not cover '.$city;
        }
        $areas = LocationResolver::related(array_map('intval', $lead->requirement?->area_ids ?? array_filter([$lead->area_id])));
        if ($areas && $profile && $profile->areas->pluck('id')->intersect($areas)->isEmpty()) {
            $problems[] = 'does not cover the requested area';
        }
        if (! $this->entitlements->allows($dealer, 'leads')) {
            $problems[] = 'has no lead allowance left this month';
        }

        return $problems;
    }

    /**
     * Staff add one more dealer to a lead (up to the per-lead maximum).
     * A dealer with no allowance left can only be added with an explicit,
     * audited override; the other checks cannot be overridden.
     */
    public function addDealer(Lead $lead, User $dealer, User $staff, string $reason, bool $overrideAllowance = false): LeadAssignment
    {
        $problems = $this->eligibilityProblems($lead, $dealer);
        if ($overrideAllowance) {
            $problems = array_values(array_diff($problems, ['has no lead allowance left this month']));
        }
        if ($problems) {
            throw new \InvalidArgumentException(($dealer->dealerProfile?->agency_name ?? $dealer->name).' '.implode(', ', $problems).'.');
        }
        if ($lead->activeAssignments()->where('dealer_id', '!=', $dealer->id)->count() >= (int) config('meezan.leads.max_dealers_per_requirement', 3)) {
            throw new \InvalidArgumentException('This lead already has the maximum number of dealers. Remove one first.');
        }
        if ($lead->activeAssignments()->where('dealer_id', $dealer->id)->exists()) {
            throw new \InvalidArgumentException('That dealer already has this lead.');
        }

        return DB::transaction(function () use ($lead, $dealer, $staff, $reason, $overrideAllowance) {
            $assignment = $this->activate($lead, $dealer, $staff, $reason);
            AuditLogger::record('lead.dealer_added', $lead, ['dealer_id' => $dealer->id, 'reason' => $reason, 'allowance_override' => $overrideAllowance ?: null], $staff->id);

            return $assignment;
        });
    }

    /** Staff take a lead away from one dealer. The assignment and its CRM history are kept, ended. */
    public function removeAssignment(LeadAssignment $assignment, User $staff, string $reason): void
    {
        if ($assignment->status !== 'active') {
            throw new \InvalidArgumentException('This assignment has already ended.');
        }
        DB::transaction(function () use ($assignment, $staff, $reason) {
            $this->endAssignment($assignment, 'removed', $staff, $reason);
            $lead = $assignment->lead;
            if (! $lead->activeAssignments()->exists()) {
                $lead->status = LeadStatus::Unrouted;
                $lead->save();
            }
            AuditLogger::record('lead.assignment_removed', $lead, ['dealer_id' => $assignment->dealer_id, 'reason' => $reason], $staff->id);
        });
    }

    /** Start (or restart) a dealer's assignment, keeping any earlier CRM history on the same row. */
    protected function activate(Lead $lead, User $dealer, User $staff, string $reason): LeadAssignment
    {
        $assignment = LeadAssignment::where('lead_id', $lead->id)->where('dealer_id', $dealer->id)->first() ?? new LeadAssignment;
        $assignment->lead_id = $lead->id;
        $assignment->dealer_id = $dealer->id;
        $assignment->fill(['score' => null, 'reason' => $reason]);
        $assignment->assigned_by = $staff->id;
        $assignment->status = 'active';
        $assignment->assigned_at = now();
        $assignment->ended_at = null;
        $assignment->end_reason = null;
        $assignment->last_activity_at = now();
        $assignment->stale_flagged_at = null;
        $assignment->save();

        $lead->status = LeadStatus::Assigned;
        $lead->hold_reason = null;
        $lead->save();

        app(DealerCrm::class)->record($assignment, 'assigned', $staff, ['reason' => $reason]);
        $dealer->notify(new LeadAssigned($assignment));

        return $assignment;
    }

    protected function endAssignment(LeadAssignment $assignment, string $status, User $staff, string $reason): void
    {
        $assignment->forceFill(['status' => $status, 'ended_at' => now(), 'end_reason' => $reason])->save();
        app(DealerCrm::class)->record($assignment, 'assignment_removed', $staff, ['reason' => $reason]);
        $assignment->dealer?->notify(new CrmReminder('assignment_removed', $assignment));
    }

    /**
     * Retry requirement leads held because every dealer was at their limit
     * (allowances reset monthly, and dealers upgrade). Run daily.
     */
    public function retryHeld(int $days = 30): int
    {
        $routed = 0;
        Lead::where('hold_reason', 'capacity')->where('status', LeadStatus::Unrouted)->whereNull('invalidated_at')
            ->whereNotNull('buyer_requirement_id')->where('created_at', '>=', now()->subDays($days))
            ->whereDoesntHave('assignments')->with('requirement')->each(function (Lead $lead) use (&$routed) {
                if ($lead->requirement && $this->routeRequirement($lead->requirement, true)?->fresh()->status === LeadStatus::Assigned) {
                    $routed++;
                }
            });

        return $routed;
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
        $assignment->last_activity_at = now();
        $assignment->save();

        $lead->status = LeadStatus::Assigned;
        $lead->save();

        AuditLogger::record('lead.assigned', $lead, ['dealer_id' => $dealerId, 'score' => $score, 'reason' => $reason], null);

        return $assignment;
    }
}
