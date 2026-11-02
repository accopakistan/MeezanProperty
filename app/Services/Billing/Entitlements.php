<?php

namespace App\Services\Billing;

use App\Enums\PropertyStatus;
use App\Models\LeadAssignment;
use App\Models\Package;
use App\Models\Property;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BillingUpdated;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * What a dealer's plan allows and how much of it is used.
 *
 * Counting rules (config/meezan.php "billing"):
 * - listings: the dealer's listings in counted_listing_statuses (by default
 *   published and awaiting review; drafts, rejected, expired, archived and
 *   suspended listings never count);
 * - leads: lead assignments this calendar month from counted_lead_sources,
 *   excluding leads staff marked as spam/duplicate/invalid and assignments
 *   that were moved to another dealer;
 * - featured: the dealer's published listings marked as featured.
 *
 * Results are memoised on this instance (resolve a fresh one per use); call flush() after a change.
 */
class Entitlements
{
    /** @var array<int, Plan> */
    protected array $plans = [];

    /** @var array<int, array<string, int>> */
    protected array $usage = [];

    public function plan(User $dealer): Plan
    {
        return $this->plans[$dealer->id] ??= $this->resolvePlan($dealer);
    }

    /** @return array{listings: int, leads: int, featured: int} */
    public function usage(User $dealer): array
    {
        return $this->usage[$dealer->id] ??= [
            'listings' => $this->countedListings($dealer->id)->count(),
            'leads' => (int) ($this->leadUsage(collect([$dealer->id]))[$dealer->id] ?? 0),
            'featured' => Property::where('user_id', $dealer->id)->where('status', PropertyStatus::Published)->where('is_featured', true)->count(),
        ];
    }

    /** null = no limit */
    public function remaining(User $dealer, string $key): ?int
    {
        $limit = $this->plan($dealer)->limit($key);

        return $limit === null ? null : max(0, $limit - ($this->usage($dealer)[$key] ?? 0));
    }

    public function allows(User $dealer, string $key, int $more = 1): bool
    {
        $remaining = $this->remaining($dealer, $key);

        return $remaining === null || $remaining >= $more;
    }

    public function isOverLimit(User $dealer, string $key): bool
    {
        $limit = $this->plan($dealer)->limit($key);

        return $limit !== null && ($this->usage($dealer)[$key] ?? 0) > $limit;
    }

    /** Whether a listing in this status uses up the allowance. */
    public static function countsListing(PropertyStatus $status): bool
    {
        return in_array($status->value, config('meezan.billing.counted_listing_statuses'), true);
    }

    public function countedListings(int $dealerId)
    {
        return Property::where('user_id', $dealerId)->whereIn('status', config('meezan.billing.counted_listing_statuses'));
    }

    /**
     * Leads used this month for many dealers in one query.
     *
     * @param  Collection<int, int>  $dealerIds
     * @return Collection<int, int> dealer id => count
     */
    public function leadUsage(Collection $dealerIds): Collection
    {
        $sources = config('meezan.billing.counted_lead_sources');

        return LeadAssignment::query()
            ->whereIn('dealer_id', $dealerIds)
            // Leads staff moved away (reassigned or removed) do not use the allowance.
            ->whereNotIn('status', ['reassigned', 'removed'])
            ->where('assigned_at', '>=', now()->startOfMonth())
            ->whereHas('lead', fn ($q) => $q->whereNull('invalidated_at')->where(fn ($s) => $s
                ->when(in_array('requirement', $sources, true), fn ($r) => $r->orWhereNotNull('buyer_requirement_id'))
                ->when(in_array('inquiry', $sources, true), fn ($r) => $r->orWhereNotNull('inquiry_id'))
                ->when(! $sources, fn ($r) => $r->whereRaw('1 = 0'))))
            ->selectRaw('dealer_id, count(*) as total')
            ->groupBy('dealer_id')
            ->pluck('total', 'dealer_id')
            ->map(fn ($n) => (int) $n);
    }

    /**
     * Plans for many dealers at once (lead routing), avoiding a query per dealer.
     *
     * @param  Collection<int, int>  $dealerIds
     * @return Collection<int, Plan>
     */
    public function plans(Collection $dealerIds): Collection
    {
        $subscriptions = Subscription::inForce()->whereIn('user_id', $dealerIds)->latest('id')->get()->unique('user_id')->keyBy('user_id');
        $default = $this->defaultPlan();

        return $dealerIds->mapWithKeys(fn ($id) => [$id => $this->plans[$id] ??= isset($subscriptions[$id]) ? Plan::fromSubscription($subscriptions[$id]) : $default]);
    }

    public function flush(?User $dealer = null): void
    {
        if ($dealer) {
            unset($this->plans[$dealer->id], $this->usage[$dealer->id]);
        } else {
            $this->plans = $this->usage = [];
        }
    }

    /**
     * Tell the dealer once per day (in-app) that a limit stopped something.
     */
    public function notifyLimitReached(User $dealer, string $key): void
    {
        if (Cache::add("billing:limit-notice:{$key}:{$dealer->id}:".now()->toDateString(), true, now()->endOfDay())) {
            $dealer->notify(new BillingUpdated($key === 'leads' ? 'lead_limit_reached' : 'listing_limit_reached', null, [
                'limit' => $this->plan($dealer)->limit($key),
            ]));
        }
    }

    protected function resolvePlan(User $dealer): Plan
    {
        $subscription = Subscription::inForce()->where('user_id', $dealer->id)->latest('id')->first();

        return $subscription ? Plan::fromSubscription($subscription) : $this->defaultPlan();
    }

    protected function defaultPlan(): Plan
    {
        $package = Package::defaultPlan();

        return $package ? Plan::fromPackage($package) : Plan::fallback();
    }
}
