<?php

namespace App\Services\Agents;

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Models\AiDraft;
use App\Models\AiKnowledgeDocument;
use App\Models\AiTask;
use App\Models\AiUsageRecord;
use App\Models\Area;
use App\Models\AreaBoundary;
use App\Models\DealerProfile;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexChange;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentLegalRecord;
use App\Models\Invoice;
use App\Models\LeadFollowUp;
use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Property;
use App\Models\StaffTask;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Billing\Entitlements;
use App\Services\Crm\CrmStats;
use App\Services\DuplicateDetector;
use App\Services\Investment\InvestmentAdvisor;
use App\Services\Investment\MarketMetrics;
use App\Services\InvestmentIndex\Coverage;
use App\Services\ListingCompleteness;
use App\Support\Cities;
use App\Support\Format;
use App\Support\Seo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

/**
 * Every tool agents can use, with its input rules and risk class. Tools
 * read through existing models and services, return aggregates and record
 * references (ids, cities, statuses) rather than private contact data, and
 * never run arbitrary SQL. Approval tools have no handler here: they only
 * create approval requests (see ApprovalExecutor for what happens after).
 */
class ToolRegistry
{
    /** @var array<string, ToolDefinition>|null */
    protected ?array $tools = null;

    public function get(string $name): ?ToolDefinition
    {
        return $this->all()[$name] ?? null;
    }

    /** @return array<string, ToolDefinition> */
    public function all(): array
    {
        return $this->tools ??= collect($this->definitions())->keyBy(fn (ToolDefinition $t) => $t->name)->all();
    }

    /** @return list<ToolDefinition> */
    protected function definitions(): array
    {
        $city = ['nullable', Rule::in(Cities::names())];
        $days = ['nullable', 'integer', 'between:1,365'];
        $limit = ['nullable', 'integer', 'between:1,100'];

        return [
            /* Properties ------------------------------------------------------ */
            new ToolDefinition('properties.search', 'Search published listings by city, purpose and type (public listing facts only).', 'read',
                ['city' => $city, 'purpose' => ['nullable', 'in:sale,rent'], 'type' => ['nullable', 'string', 'max:30'], 'max_price' => ['nullable', 'integer', 'min:1'], 'limit' => $limit],
                fn (array $i) => $this->propertySearch($i), 'properties'),
            new ToolDefinition('properties.inventory', 'Listing counts by city and status, and published listings by type.', 'read', ['city' => $city],
                fn (array $i) => $this->inventory($i['city'] ?? null), 'properties'),
            new ToolDefinition('properties.incomplete', 'Listings missing information (completeness check) in a city, or one listing.', 'read', ['city' => $city, 'limit' => $limit, 'property_id' => ['nullable', 'integer']],
                fn (array $i) => $this->incomplete($i), 'properties'),
            new ToolDefinition('properties.duplicates', 'Duplicate-listing candidates among recent listings, or for one listing.', 'read', ['city' => $city, 'limit' => $limit, 'property_id' => ['nullable', 'integer']],
                fn (array $i) => $this->duplicates($i), 'properties'),
            new ToolDefinition('properties.price_anomalies', 'Published listings priced far from the median per sq ft of the same city, purpose and type.', 'read', ['city' => $city],
                fn (array $i) => $this->priceAnomalies($i['city'] ?? null), 'properties'),
            new ToolDefinition('properties.stale', 'Listings expiring soon, and pending listings waiting for review longest.', 'read', ['city' => $city, 'days' => $days],
                fn (array $i) => $this->stale($i), 'properties'),
            new ToolDefinition('city.statistics', 'Per-city platform figures: live listings, areas, verified dealers, leads in the period.', 'read', ['days' => $days],
                fn (array $i) => $this->cityStatistics((int) ($i['days'] ?? 30)), 'business'),

            /* Dealers ------------------------------------------------------------ */
            new ToolDefinition('dealers.pending_verification', 'Dealers whose verification is not complete, with document status (no document contents).', 'read', ['city' => $city],
                fn (array $i) => $this->pendingDealers($i['city'] ?? null), 'dealers'),
            new ToolDefinition('dealers.performance', 'Lead handling per dealer over a period: received, contacted, qualified, won, lost, stale.', 'read', ['days' => $days, 'city' => $city, 'limit' => $limit],
                fn (array $i) => $this->dealerPerformance($i), 'dealers'),
            new ToolDefinition('dealers.inactive', 'Verified dealers with no listing update and no lead activity in the period.', 'read', ['days' => $days],
                fn (array $i) => $this->inactiveDealers((int) ($i['days'] ?? 30)), 'dealers'),

            new ToolDefinition('dealers.listing_quality', 'Per dealer: live listings, average completeness, low-scoring listings and profile gaps.', 'read', ['city' => $city],
                fn (array $i) => $this->dealerQuality($i['city'] ?? null), 'dealers'),

            /* Leads & CRM --------------------------------------------------------- */
            new ToolDefinition('leads.summary', 'Lead counts by status, source and city for a period.', 'read', ['days' => $days, 'city' => $city],
                fn (array $i) => $this->leadSummary((int) ($i['days'] ?? 30), $i['city'] ?? null), 'leads'),
            new ToolDefinition('crm.follow_ups_due', 'Open CRM follow-ups due today or overdue (lead references, no buyer details).', 'read', ['limit' => $limit],
                fn (array $i) => $this->followUpsDue((int) ($i['limit'] ?? 50)), 'leads'),
            new ToolDefinition('crm.pipeline', 'Active lead assignments by pipeline stage.', 'read', [],
                fn () => $this->pipeline(), 'leads'),
            new ToolDefinition('crm.outcomes', 'First-response time, assignments waiting for first contact, lost-lead reasons and upcoming appointments (no buyer details).', 'read', ['days' => $days],
                fn (array $i) => $this->crmOutcomes((int) ($i['days'] ?? 90)), 'leads'),
            new ToolDefinition('dealer.self_summary', "The requesting dealer's own leads and subscription (only for that dealer).", 'read', [],
                fn (array $i, AgentContext $ctx) => $this->dealerSelf($ctx), 'dealers'),

            /* Subscriptions & finance ------------------------------------------- */
            new ToolDefinition('subscriptions.summary', 'Subscriptions by status and package; trial and paid counts (test records excluded).', 'read', [],
                fn () => $this->subscriptionSummary(), 'finance'),
            new ToolDefinition('subscriptions.expiring', 'Subscriptions whose period ends within N days, and past-due ones.', 'read', ['days' => $days],
                fn (array $i) => $this->expiring((int) ($i['days'] ?? 14)), 'finance'),
            new ToolDefinition('subscriptions.utilization', 'Listing and lead allowance use per dealer under their current package.', 'read', ['limit' => $limit],
                fn (array $i) => $this->utilization((int) ($i['limit'] ?? 50)), 'finance'),
            new ToolDefinition('payments.revenue', 'Succeeded payments, refunds and pending amounts for a month (test records excluded).', 'read', ['month' => ['nullable', 'date_format:Y-m']],
                fn (array $i) => $this->revenue($i['month'] ?? now()->format('Y-m')), 'finance'),
            new ToolDefinition('payments.outstanding', 'Unpaid invoices and payments awaiting review.', 'read', [],
                fn () => $this->outstanding(), 'finance'),

            new ToolDefinition('payments.reconciliation', 'Compares paid invoices with succeeded payments for a month and lists mismatches (test records excluded).', 'read', ['month' => ['nullable', 'date_format:Y-m']],
                fn (array $i) => $this->reconciliation($i['month'] ?? now()->format('Y-m')), 'finance'),

            /* Investment & market data ---------------------------------------- */
            new ToolDefinition('investment.rankings', 'Published investment rankings (score, confidence, risk, change, yield) by city and category.', 'read',
                ['city' => $city, 'category' => ['nullable', Rule::in(array_keys(config('meezan.investment.categories')))], 'limit' => $limit],
                fn (array $i) => $this->rankings($i), 'investment'),
            new ToolDefinition('investment.compare', 'Published investment data for named areas/projects; reports which have no published data.', 'read',
                ['names' => ['required', 'array', 'min:1', 'max:4'], 'names.*' => ['string', 'max:80'], 'city' => $city],
                fn (array $i) => $this->compareInvestments($i['names'], $i['city'] ?? null), 'investment'),
            new ToolDefinition('investment.yield', 'Gross (and net when costs are given) rental yield from a price and monthly rent.', 'read',
                ['price' => ['required', 'integer', 'min:1'], 'monthly_rent' => ['required', 'integer', 'min:1'], 'annual_costs' => ['nullable', 'integer', 'min:0']],
                fn (array $i) => $this->yield($i), 'investment'),
            new ToolDefinition('investment.advisor', 'Answer an investment question from the published rankings by city and property type (societies, plots, malls, towers …), with scores, confidence, sources and what is missing; says when the data is demo data.', 'read',
                ['question' => ['required', 'string', 'min:3', 'max:300'], 'city' => $city, 'segment' => ['nullable', Rule::in(array_keys(config('meezan.investment.segments')))],
                    'focus' => ['nullable', Rule::in(['best', 'compare', 'rental', 'risk', 'forecast'])]],
                fn (array $i) => $this->advisor($i), 'investment'),
            new ToolDefinition('investment_index.leaderboard', 'Meezan Investment Index leaderboard for one category (housing societies, commercial plazas, apartment buildings, shopping malls, business towers): ranked projects with score, Data Confidence Score, risk, legal status and last verification, plus how many have Insufficient Verified Data or are excluded. Scores come only from the Index engine.', 'read',
                ['category' => ['required', Rule::in(array_keys(config('investment_index.categories')))], 'limit' => $limit],
                fn (array $i) => $this->indexLeaderboard($i['category'], (int) ($i['limit'] ?? 10)), 'investment'),
            new ToolDefinition('investment_index.project', 'One development\'s Meezan Investment Index profile: status (ranked, not eligible, Insufficient Verified Data, excluded), score, ranks, each factor with its verified value or why it is missing, risks, legal status and reasons.', 'read',
                ['name' => ['required', 'string', 'min:2', 'max:120']],
                fn (array $i) => $this->indexProject($i['name']), 'investment'),
            new ToolDefinition('investment_index.coverage', 'Real-world investment data coverage: genuine projects per city and category, verified legal, operational, price and rental coverage, the source registry, collection status, records waiting for staff, possible duplicate projects. Actual counts only.', 'read', [],
                fn () => app(Coverage::class)->forAgents(), 'investment'),
            new ToolDefinition('market_data.quality', 'Market Data Centre status: records by status and city, sources by reliability, last calculation.', 'read', [],
                fn () => $this->marketQuality(), 'investment'),
            new ToolDefinition('locations.coverage', 'Location data coverage per city: areas, centres, boundaries, projects.', 'read', [],
                fn () => $this->locationCoverage(), 'locations'),

            new ToolDefinition('locations.review_queue', 'Location records needing attention: flagged, no map centre, unverified approval, possible duplicates.', 'read', ['city' => $city],
                fn (array $i) => $this->locationReview($i['city'] ?? null), 'locations'),

            /* Growth ------------------------------------------------------------- */
            new ToolDefinition('marketing.funnel', 'Platform funnel figures per city: listings, inquiries and leads by source in the period (no campaign data is connected).', 'read', ['days' => $days],
                fn (array $i) => $this->funnel((int) ($i['days'] ?? 30)), 'marketing'),
            new ToolDefinition('seo.audit', 'Internal SEO status: sitemap URL counts, indexable category pages, published listings without photos or descriptions.', 'read', [],
                fn () => $this->seoAudit(), 'marketing'),
            new ToolDefinition('connectors.status', 'Configuration status of external integrations (never reports "connected" without a check).', 'read', [],
                fn () => app(ConnectorStatus::class)->all(), 'system'),

            /* System & staff ------------------------------------------------------ */
            new ToolDefinition('system.health', 'Read-only diagnostics: failed and queued jobs, recent error log summary (redacted), migrations, cache state.', 'read', [],
                fn () => $this->health(), 'system'),
            new ToolDefinition('ai.usage', 'Agent task and token usage, with cost only where model pricing is configured.', 'read', ['days' => $days],
                fn (array $i) => $this->aiUsage((int) ($i['days'] ?? 30)), 'system'),
            new ToolDefinition('staff.directory', 'Staff (admin) accounts and their open internal tasks; names and roles only.', 'read', [],
                fn () => $this->staffDirectory(), 'staff'),
            new ToolDefinition('knowledge.search', 'Search approved knowledge-base documents this agent may read.', 'read',
                ['query' => ['required', 'string', 'max:300'], 'category' => ['nullable', Rule::in(array_keys(AiKnowledgeDocument::CATEGORIES))], 'limit' => ['nullable', 'integer', 'between:1,5']],
                fn (array $i, AgentContext $ctx) => app(KnowledgeBase::class)->search($i['query'], $ctx->agent->slug, $i['category'] ?? null, (int) ($i['limit'] ?? 3), $ctx->agent->slug === 'customer-support' || $ctx->forCustomer() ? AiKnowledgeDocument::PUBLIC_CATEGORIES : null), 'knowledge'),

            /* Low-risk actions ---------------------------------------------------- */
            new ToolDefinition('support.create_ticket', 'Open a support ticket for staff follow-up.', 'low',
                ['subject' => ['required', 'string', 'max:200'], 'summary' => ['required', 'string', 'max:2000'], 'category' => ['required', 'in:account,listing,dealer,subscription,complaint,payment,technical,other'], 'priority' => ['nullable', 'in:low,normal,high']],
                fn (array $i, AgentContext $ctx) => $this->ticket($i, $ctx), 'support'),
            new ToolDefinition('staff.create_task', 'Create an internal task for staff (no action is taken by the AI).', 'low',
                ['title' => ['required', 'string', 'max:200'], 'description' => ['required', 'string', 'max:2000'], 'category' => ['required', 'string', 'max:30'], 'due_in_days' => ['nullable', 'integer', 'between:0,60'],
                    'related_type' => ['nullable', 'string', 'max:40'], 'related_id' => ['nullable', 'integer']],
                fn (array $i, AgentContext $ctx) => $this->staffTask($i, $ctx), 'staff'),
            new ToolDefinition('listings.flag', 'Flag a listing for staff review (creates a staff task; does not change the listing).', 'low',
                ['property_id' => ['required', 'integer', 'exists:properties,id'], 'reason' => ['required', 'string', 'max:500']],
                fn (array $i, AgentContext $ctx) => $this->staffTask(['title' => 'Review listing #'.$i['property_id'], 'description' => $i['reason'], 'category' => 'listing_review', 'related_type' => 'property', 'related_id' => $i['property_id'], 'due_in_days' => 3], $ctx), 'properties'),
            new ToolDefinition('content.save_draft', 'Save draft content (marketing, social, SEO brief, email, proposal). Drafts are never published by this tool.', 'low',
                ['type' => ['required', 'in:marketing,social,seo_brief,email,proposal,reminder'], 'channel' => ['nullable', 'string', 'max:30'], 'title' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:10000']],
                fn (array $i, AgentContext $ctx) => ['draft_id' => AiDraft::create($i + ['task_id' => $ctx->task->id, 'agent_id' => $ctx->agent->id, 'status' => 'draft'])->id, 'status' => 'draft'], 'content'),

            /* Approval required --------------------------------------------------- */
            new ToolDefinition('content.publish', 'Publish a draft to a connected channel.', 'approval', ['draft_id' => ['required', 'integer', 'exists:ai_drafts,id'], 'channel' => ['required', 'string', 'max:30']],
                describe: fn (array $i) => "Publish draft #{$i['draft_id']} to {$i['channel']}."),
            new ToolDefinition('communications.send_bulk', 'Send a message to a group of users (consent rules apply).', 'approval', ['audience' => ['required', 'string', 'max:120'], 'draft_id' => ['required', 'integer', 'exists:ai_drafts,id']],
                describe: fn (array $i) => "Send draft #{$i['draft_id']} to: {$i['audience']}."),
            new ToolDefinition('leads.reassign', 'Move a lead assignment to another dealer.', 'approval', ['lead_id' => ['required', 'integer', 'exists:leads,id'], 'dealer_id' => ['required', 'integer', 'exists:users,id'], 'reason' => ['required', 'string', 'max:500']],
                describe: fn (array $i) => "Reassign lead #{$i['lead_id']} to dealer #{$i['dealer_id']}: {$i['reason']}"),
            new ToolDefinition('dealers.verify', 'Request a verification decision for a dealer (documents are reviewed by staff).', 'approval', ['dealer_id' => ['required', 'integer', 'exists:users,id'], 'recommendation' => ['required', 'string', 'max:500']],
                describe: fn (array $i) => "Verification review for dealer #{$i['dealer_id']}: {$i['recommendation']}"),
            new ToolDefinition('subscriptions.change', 'Change a dealer package or entitlement.', 'approval', ['subscription_id' => ['required', 'integer', 'exists:subscriptions,id'], 'change' => ['required', 'string', 'max:500']],
                describe: fn (array $i) => "Subscription #{$i['subscription_id']}: {$i['change']}"),
            new ToolDefinition('investment.methodology_change', 'Propose new ranking weights (creates a draft methodology version after approval).', 'approval', ['weights' => ['required', 'array'], 'reason' => ['required', 'string', 'max:500']],
                describe: fn (array $i) => 'New draft investment methodology: '.$i['reason']),

            /* Prohibited: listed so requests are recognised and refused ------------ */
            new ToolDefinition('payments.refund', 'Issue a refund.', 'prohibited', []),
            new ToolDefinition('payments.transfer', 'Transfer funds.', 'prohibited', []),
            new ToolDefinition('records.delete', 'Delete business records.', 'prohibited', []),
            new ToolDefinition('system.shell', 'Run shell commands.', 'prohibited', []),
            new ToolDefinition('system.deploy', 'Deploy code to production.', 'prohibited', []),
        ];
    }

    /* Implementations --------------------------------------------------------- */

    protected function since(int $days): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($days);
    }

    protected function propertySearch(array $i): array
    {
        $rows = Property::query()->published()->with('area:id,name')
            ->when($i['city'] ?? null, fn ($q, $c) => $q->where('city', $c))
            ->when($i['purpose'] ?? null, fn ($q, $p) => $q->where('purpose', $p))
            ->when($i['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($i['max_price'] ?? null, fn ($q, $p) => $q->where('price', '<=', $p))
            ->latest('published_at')->limit((int) ($i['limit'] ?? 10))
            ->get(['id', 'slug', 'title', 'city', 'area_id', 'purpose', 'type', 'price', 'is_demo']);

        return ['count' => $rows->count(), 'listings' => $rows->map(fn ($p) => [
            'id' => $p->id, 'title' => $p->title, 'city' => $p->city, 'area' => $p->area?->name, 'purpose' => $p->purpose->value,
            'type' => $p->type->value, 'price' => Format::price($p->price), 'demo' => (bool) $p->is_demo, 'url' => route('properties.show', $p->slug),
        ])->all()];
    }

    protected function inventory(?string $city): array
    {
        $q = fn () => Property::query()->when($city, fn ($x) => $x->where('city', $city));

        return [
            'by_city_status' => $q()->toBase()->selectRaw('city, status, count(*) as n')->groupBy('city', 'status')->get()
                ->groupBy('city')->map(fn ($g) => $g->pluck('n', 'status')->map(fn ($n) => (int) $n))->all(),
            'published_by_type' => $q()->published()->toBase()->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type')->map(fn ($n) => (int) $n)->all(),
            'published_total' => $q()->published()->count(),
            'demo_published' => $q()->published()->where('is_demo', true)->count(),
            'pending_review' => $q()->where('status', PropertyStatus::Pending)->count(),
        ];
    }

    protected function incomplete(array $i): array
    {
        $rows = Property::query()->whereIn('status', [PropertyStatus::Published, PropertyStatus::Pending, PropertyStatus::ChangesRequested])
            ->when($i['city'] ?? null, fn ($q, $c) => $q->where('city', $c))
            ->when($i['property_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->with(['images' => fn ($q) => $q->where('status', 'active')->select(['id', 'property_id', 'width', 'status'])])
            ->latest('id')->limit(300)->get();
        $incomplete = $rows->map(fn ($p) => ['property' => $p, 'check' => ListingCompleteness::for($p)])
            ->filter(fn ($r) => ! empty($r['check']['missing']))->sortBy(fn ($r) => $r['check']['score'])->values();

        return [
            'checked' => $rows->count(),
            'incomplete' => $incomplete->count(),
            'listings' => $incomplete->take((int) ($i['limit'] ?? 20))->map(fn ($r) => [
                'id' => $r['property']->id, 'title' => $r['property']->title, 'city' => $r['property']->city, 'status' => $r['property']->status->value,
                'score' => $r['check']['score'], 'missing' => $r['check']['missing'],
            ])->all(),
        ];
    }

    protected function duplicates(array $i): array
    {
        $detector = app(DuplicateDetector::class);
        $recent = Property::query()->whereIn('status', [PropertyStatus::Published, PropertyStatus::Pending])
            ->when($i['city'] ?? null, fn ($q, $c) => $q->where('city', $c))->when($i['property_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->latest('id')->limit(50)->get();
        $pairs = [];
        foreach ($recent as $p) {
            foreach ($detector->candidates($p) as $other) {
                $key = min($p->id, $other->id).'-'.max($p->id, $other->id);
                $pairs[$key] ??= ['listing_ids' => [min($p->id, $other->id), max($p->id, $other->id)], 'city' => $p->city, 'reasons' => $detector->reasons($p, $other)];
            }
        }

        return ['checked' => $recent->count(), 'candidates' => array_values(array_slice($pairs, 0, (int) ($i['limit'] ?? 20)))];
    }

    protected function priceAnomalies(?string $city): array
    {
        $rows = Property::query()->published()->where('is_demo', false)->whereNotNull('area_sqft')->where('area_sqft', '>', 0)
            ->when($city, fn ($q) => $q->where('city', $city))->limit(2000)->get(['id', 'title', 'city', 'purpose', 'type', 'price', 'area_sqft']);
        $flags = [];
        foreach ($rows->groupBy(fn ($p) => $p->city.'|'.$p->purpose->value.'|'.$p->type->value) as $key => $group) {
            if ($group->count() < 5) {
                continue;
            }
            $perSqft = $group->map(fn ($p) => $p->price / $p->area_sqft)->sort()->values();
            $median = $perSqft[intdiv($perSqft->count(), 2)];
            foreach ($group as $p) {
                $ratio = ($p->price / $p->area_sqft) / $median;
                if ($ratio >= 3 || $ratio <= 0.33) {
                    $flags[] = ['id' => $p->id, 'title' => $p->title, 'group' => $key, 'times_median' => round($ratio, 2)];
                }
            }
        }

        return ['checked' => $rows->count(), 'groups_with_enough_data' => $rows->groupBy(fn ($p) => $p->city.'|'.$p->purpose->value.'|'.$p->type->value)->filter(fn ($g) => $g->count() >= 5)->count(),
            'rule' => 'Price per sq ft at least 3× or at most ⅓ of the median of the same city, purpose and type (groups of 5+ real listings).', 'anomalies' => $flags];
    }

    protected function stale(array $i): array
    {
        $days = (int) ($i['days'] ?? 14);
        $base = fn () => Property::query()->when($i['city'] ?? null, fn ($q, $c) => $q->where('city', $c));

        return [
            'expiring_within_days' => $days,
            'expiring' => $base()->published()->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays($days))->orderBy('expires_at')->limit(30)
                ->get(['id', 'title', 'city', 'expires_at'])->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'city' => $p->city, 'expires' => $p->expires_at?->toDateString()])->all(),
            'oldest_pending' => $base()->where('status', PropertyStatus::Pending)->oldest('updated_at')->limit(20)
                ->get(['id', 'title', 'city', 'updated_at'])->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'city' => $p->city, 'waiting_since' => $p->updated_at?->toDateString()])->all(),
        ];
    }

    protected function cityStatistics(int $days): array
    {
        $since = $this->since($days);
        $listings = Property::query()->published()->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city');
        $real = Property::query()->published()->where('is_demo', false)->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city');
        $areas = Area::active()->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city');
        $dealers = DealerProfile::where('verification_status', DealerStatus::Verified)->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city');
        $leads = $this->leadCityQuery()->where('leads.created_at', '>=', $since)->selectRaw('count(*) as n')->groupBy('lead_city')->pluck('n', 'lead_city');

        return ['period_days' => $days, 'cities' => collect(Cities::names())->mapWithKeys(fn ($c) => [$c => [
            'live_listings' => (int) ($listings[$c] ?? 0), 'real_listings' => (int) ($real[$c] ?? 0), 'areas' => (int) ($areas[$c] ?? 0),
            'verified_dealers' => (int) ($dealers[$c] ?? 0), 'leads' => (int) ($leads[$c] ?? 0),
        ]])->all()];
    }

    /** Leads with their city: from the area, the listing or the requirement. */
    protected function leadCityQuery()
    {
        return DB::table('leads')
            ->leftJoin('areas', 'areas.id', '=', 'leads.area_id')
            ->leftJoin('properties', 'properties.id', '=', 'leads.property_id')
            ->leftJoin('buyer_requirements', 'buyer_requirements.id', '=', 'leads.buyer_requirement_id')
            ->selectRaw('coalesce(areas.city, properties.city, buyer_requirements.city) as lead_city');
    }

    protected function pendingDealers(?string $city): array
    {
        $profiles = DealerProfile::query()->whereNotIn('verification_status', [DealerStatus::Verified])
            ->when($city, fn ($q) => $q->where('city', $city))
            ->withCount(['documents as documents_pending' => fn ($q) => $q->where('status', 'pending'), 'documents as documents_approved' => fn ($q) => $q->where('status', 'approved'), 'documents as documents_rejected' => fn ($q) => $q->where('status', 'rejected')])
            ->oldest('created_at')->limit(50)->get();

        return ['count' => $profiles->count(), 'dealers' => $profiles->map(fn ($p) => [
            'dealer_id' => $p->user_id, 'agency' => $p->agency_name, 'city' => $p->city, 'status' => $p->verification_status instanceof \BackedEnum ? $p->verification_status->value : $p->verification_status,
            'submitted' => $p->verification_submitted_at?->toDateString(), 'documents' => ['pending' => $p->documents_pending, 'approved' => $p->documents_approved, 'rejected' => $p->documents_rejected],
            'missing' => $p->documents_pending + $p->documents_approved === 0 ? 'No current documents uploaded' : null,
        ])->all()];
    }

    protected function dealerPerformance(array $i): array
    {
        $days = (int) ($i['days'] ?? 30);
        $dealers = DealerProfile::query()->where('verification_status', DealerStatus::Verified)->when($i['city'] ?? null, fn ($q, $c) => $q->where('city', $c))->get(['user_id', 'agency_name', 'city']);
        $perf = app(CrmStats::class)->performance($dealers->pluck('user_id'), now()->subDays($days));

        return ['period_days' => $days, 'dealers' => $dealers->map(function ($d) use ($perf) {
            $p = $perf[$d->user_id] ?? [];

            return ['dealer_id' => $d->user_id, 'agency' => $d->agency_name, 'city' => $d->city] + collect($p)->only(['received', 'contacted', 'qualified', 'viewings', 'won', 'lost', 'stale', 'followups_overdue', 'contact_rate'])->all();
        })->sortByDesc('received')->take((int) ($i['limit'] ?? 25))->values()->all()];
    }

    protected function inactiveDealers(int $days): array
    {
        $since = $this->since($days);
        $active = DB::table('properties')->where('updated_at', '>=', $since)->pluck('user_id')
            ->merge(DB::table('lead_assignments')->where('last_activity_at', '>=', $since)->pluck('dealer_id'))->unique();
        $dealers = DealerProfile::where('verification_status', DealerStatus::Verified)->whereNotIn('user_id', $active)->get(['user_id', 'agency_name', 'city', 'verified_at']);

        return ['period_days' => $days, 'count' => $dealers->count(), 'dealers' => $dealers->map(fn ($d) => ['dealer_id' => $d->user_id, 'agency' => $d->agency_name, 'city' => $d->city])->all()];
    }

    protected function dealerQuality(?string $city): array
    {
        $low = 60;
        $profiles = DealerProfile::query()->when($city, fn ($q) => $q->where('city', $city))->limit(100)->get(['user_id', 'agency_name', 'city', 'office_address', 'bio', 'public_phone', 'whatsapp_number']);
        $listings = Property::query()->published()->whereIn('user_id', $profiles->pluck('user_id'))->toBase()
            ->selectRaw('user_id, count(*) as n, avg(completeness_score) as avg_score, sum(case when completeness_score < ? then 1 else 0 end) as low', [$low])
            ->groupBy('user_id')->get()->keyBy('user_id');

        return ['low_score_below' => $low, 'dealers' => $profiles->map(function ($p) use ($listings) {
            $l = $listings[$p->user_id] ?? null;

            return ['dealer_id' => $p->user_id, 'agency' => $p->agency_name, 'city' => $p->city, 'live_listings' => (int) ($l->n ?? 0),
                'average_completeness' => isset($l->avg_score) ? (int) round($l->avg_score) : null, 'low_completeness' => (int) ($l->low ?? 0),
                'profile_gaps' => array_values(array_filter([blank($p->office_address) ? 'office address' : null, blank($p->bio) ? 'description' : null, blank($p->public_phone) && blank($p->whatsapp_number) ? 'public contact number' : null]))];
        })->sortByDesc('low_completeness')->values()->all()];
    }

    protected function crmOutcomes(int $days): array
    {
        $since = $this->since($days);
        $base = fn () => DB::table('lead_assignments')->where('assigned_at', '>=', $since);
        $hours = $base()->whereNotNull('first_contact_at')->limit(1000)->get(['assigned_at', 'first_contact_at'])
            ->map(fn ($a) => CarbonImmutable::parse($a->assigned_at)->diffInMinutes(CarbonImmutable::parse($a->first_contact_at)) / 60)->sort()->values();

        return [
            'period_days' => $days,
            'responded' => $hours->count(),
            'first_response_hours_median' => $hours->isEmpty() ? null : round($hours[intdiv($hours->count(), 2)], 1),
            'awaiting_first_contact' => DB::table('lead_assignments')->where('status', 'active')->whereNull('first_contact_at')->where('assigned_at', '<', now()->subDay())->count(),
            'lost_by_reason' => $base()->where('pipeline_stage', 'lost')->selectRaw("coalesce(outcome_reason, 'not recorded') as lost_reason, count(*) as n")->groupBy('lost_reason')->pluck('n', 'lost_reason')->map(fn ($n) => (int) $n)->all(),
            'appointments_next_7_days' => DB::table('lead_appointments')->where('status', 'scheduled')->whereBetween('starts_at', [now(), now()->addDays(7)])->count(),
        ];
    }

    protected function leadSummary(int $days, ?string $city): array
    {
        $q = $this->leadCityQuery()->where('leads.created_at', '>=', $this->since($days))->when($city, fn ($x) => $x->whereRaw('coalesce(areas.city, properties.city, buyer_requirements.city) = ?', [$city]));

        return [
            'period_days' => $days,
            'total' => (clone $q)->count(),
            'by_status' => (clone $q)->addSelect('leads.status')->get()->countBy('status')->all(),
            'by_source' => (clone $q)->addSelect('leads.source')->get()->countBy('source')->all(),
            'by_city' => (clone $q)->get()->countBy(fn ($r) => $r->lead_city ?? 'Unknown')->all(),
        ];
    }

    protected function followUpsDue(int $limit): array
    {
        $rows = LeadFollowUp::query()->where('status', 'pending')->where('due_at', '<=', now()->endOfDay())
            ->with('assignment:id,lead_id,dealer_id,pipeline_stage')->orderBy('due_at')->limit($limit)->get();

        return ['count' => $rows->count(), 'overdue' => $rows->where('due_at', '<', now())->count(), 'follow_ups' => $rows->map(fn ($f) => [
            'follow_up_id' => $f->id, 'lead_id' => $f->assignment?->lead_id, 'dealer_id' => $f->dealer_id, 'type' => $f->type, 'priority' => $f->priority,
            'due' => $f->due_at?->toDateTimeString(), 'overdue' => $f->due_at?->isPast(), 'stage' => $f->assignment?->pipeline_stage?->value ?? $f->assignment?->pipeline_stage,
        ])->all()];
    }

    protected function pipeline(): array
    {
        return ['active_by_stage' => DB::table('lead_assignments')->where('status', 'active')->selectRaw('pipeline_stage, count(*) as n')->groupBy('pipeline_stage')->pluck('n', 'pipeline_stage')->map(fn ($n) => (int) $n)->all(),
            'won_last_30_days' => DB::table('lead_assignments')->where('pipeline_stage', 'won')->where('closed_at', '>=', now()->subDays(30))->count(),
            'stale_active' => DB::table('lead_assignments')->where('status', 'active')->whereNotNull('stale_flagged_at')->count()];
    }

    protected function dealerSelf(AgentContext $ctx): array
    {
        $user = $ctx->user;
        if (! $user || ! $user->isDealer()) {
            return ['available' => false, 'reason' => 'Only a signed-in dealer can see their own figures.'];
        }
        $ent = app(Entitlements::class);
        $plan = $ent->plan($user);

        return [
            'available' => true,
            'package' => $plan->name,
            'usage' => $ent->usage($user),
            'open_leads' => DB::table('lead_assignments')->where('dealer_id', $user->id)->where('status', 'active')->count(),
            'follow_ups_due' => LeadFollowUp::where('dealer_id', $user->id)->where('status', 'pending')->where('due_at', '<=', now()->endOfDay())->count(),
            'subscription_ends' => Subscription::where('user_id', $user->id)->whereIn('status', ['active', 'trial', 'past_due'])->latest('id')->value('current_period_end'),
        ];
    }

    protected function subscriptionSummary(): array
    {
        $base = fn () => Subscription::query()->where('is_test', false);

        return [
            'by_status' => $base()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all(),
            'active_by_package' => $base()->whereIn('subscriptions.status', ['active', 'trial'])->join('packages', 'packages.id', '=', 'subscriptions.package_id')
                ->toBase()->selectRaw('packages.name as package, count(*) as n')->groupBy('packages.name')->pluck('n', 'package')->map(fn ($n) => (int) $n)->all(),
            'test_records_excluded' => Subscription::where('is_test', true)->count(),
        ];
    }

    protected function expiring(int $days): array
    {
        $rows = Subscription::query()->where('is_test', false)->whereIn('status', ['active', 'trial', 'past_due'])
            ->where(fn ($q) => $q->where('current_period_end', '<=', now()->addDays($days))->orWhere('status', 'past_due'))
            ->with('package:id,name')->orderBy('current_period_end')->limit(100)->get(['id', 'user_id', 'package_id', 'status', 'current_period_end', 'cancel_at_period_end']);

        return ['within_days' => $days, 'count' => $rows->count(), 'subscriptions' => $rows->map(fn ($s) => [
            'subscription_id' => $s->id, 'dealer_id' => $s->user_id, 'package' => $s->package?->name, 'status' => $s->status instanceof \BackedEnum ? $s->status->value : $s->status,
            'period_end' => $s->current_period_end?->toDateString(), 'cancels_at_period_end' => (bool) $s->cancel_at_period_end,
        ])->all()];
    }

    protected function utilization(int $limit): array
    {
        $ent = app(Entitlements::class);
        $dealers = User::where('role', 'dealer')->where('status', 'active')->limit($limit)->get();

        return ['dealers' => $dealers->map(function (User $d) use ($ent) {
            $plan = $ent->plan($d);
            $usage = $ent->usage($d);

            return ['dealer_id' => $d->id, 'package' => $plan->name, 'listings' => $usage['listings'], 'listing_limit' => $plan->limit('listings'),
                'leads' => $usage['leads'], 'lead_limit' => $plan->limit('leads')];
        })->all()];
    }

    protected function revenue(string $month): array
    {
        $from = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $to = $from->endOfMonth();
        $paid = Payment::query()->where('is_test', false)->where('status', 'succeeded')->whereBetween('confirmed_at', [$from, $to]);

        return [
            'month' => $month,
            'currency' => 'PKR',
            'succeeded_payments' => (clone $paid)->count(),
            'succeeded_amount' => (int) (clone $paid)->sum('amount'),
            'by_gateway' => (clone $paid)->toBase()->selectRaw('gateway, sum(amount) as n')->groupBy('gateway')->pluck('n', 'gateway')->map(fn ($n) => (int) $n)->all(),
            'refunded_amount' => (int) Invoice::query()->where('is_test', false)->whereBetween('updated_at', [$from, $to])->sum('refunded_amount'),
            'pending_payments' => Payment::query()->where('is_test', false)->whereIn('status', ['pending', 'awaiting_review'])->count(),
            'test_payments_excluded' => Payment::where('is_test', true)->whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    protected function outstanding(): array
    {
        $invoices = Invoice::query()->where('is_test', false)->whereIn('status', ['pending', 'failed'])->orderBy('due_at')->limit(100)->get(['id', 'number', 'user_id', 'amount', 'status', 'due_at']);

        return [
            'unpaid_invoices' => $invoices->count(),
            'unpaid_amount' => (int) $invoices->sum('amount'),
            'overdue' => $invoices->filter(fn ($i) => $i->due_at && $i->due_at->isPast())->count(),
            'awaiting_review_payments' => Payment::where('is_test', false)->where('status', 'awaiting_review')->count(),
            'invoices' => $invoices->map(fn ($i) => ['number' => $i->number, 'dealer_id' => $i->user_id, 'amount' => (int) $i->amount, 'status' => $i->status instanceof \BackedEnum ? $i->status->value : $i->status, 'due' => $i->due_at?->toDateString()])->all(),
        ];
    }

    protected function reconciliation(string $month): array
    {
        $from = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $to = $from->endOfMonth();
        $paisa = fn ($amount) => (int) round((float) $amount * 100);
        $invoices = Invoice::query()->where('is_test', false)->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->withSum(['payments as paid_sum' => fn ($q) => $q->where('status', 'succeeded')], 'amount')->limit(500)->get();
        $payments = Payment::query()->where('is_test', false)->where('status', 'succeeded')->whereBetween('confirmed_at', [$from, $to])->with('invoice:id,number,status')->limit(500)->get();
        $mismatches = [];
        foreach ($invoices as $inv) {
            if ($paisa($inv->paid_sum) !== $paisa($inv->amount)) {
                $mismatches[] = ['reference' => $inv->number, 'issue' => 'Invoice is paid for PKR '.number_format((float) $inv->amount).' but succeeded payments total PKR '.number_format((float) $inv->paid_sum).'.'];
            }
        }
        foreach ($payments as $p) {
            if (! in_array($p->invoice?->status?->value, ['paid', 'refunded'], true)) {
                $mismatches[] = ['reference' => $p->reference, 'issue' => 'Payment succeeded but its invoice '.($p->invoice?->number ?? '?').' is '.($p->invoice?->status?->value ?? 'missing').'.'];
            }
        }
        $adjustments = DB::table('billing_adjustments')->join('invoices', 'invoices.id', '=', 'billing_adjustments.invoice_id')->where('invoices.is_test', false)->whereBetween('billing_adjustments.created_at', [$from, $to]);

        return [
            'month' => $month, 'currency' => 'PKR',
            'paid_invoices' => $invoices->count(), 'paid_invoice_amount' => (int) $invoices->sum('amount'),
            'succeeded_payments' => $payments->count(), 'succeeded_amount' => (int) $payments->sum('amount'),
            'refunds_recorded' => (int) (clone $adjustments)->where('billing_adjustments.type', 'refund')->sum('billing_adjustments.amount'),
            'credits_recorded' => (int) (clone $adjustments)->where('billing_adjustments.type', 'credit')->sum('billing_adjustments.amount'),
            'mismatches' => $mismatches,
            'test_records_excluded' => Invoice::where('is_test', true)->whereBetween('paid_at', [$from, $to])->count() + Payment::where('is_test', true)->whereBetween('confirmed_at', [$from, $to])->count(),
        ];
    }

    protected function rankings(array $i): array
    {
        $calc = InvestmentCalculation::current();
        if (! $calc) {
            return ['published' => false, 'reason' => 'No investment rankings have been published (no verified market data has been published yet).'];
        }
        $rows = $calc->rankings()->where('category', $i['category'] ?? 'overall')->when($i['city'] ?? null, fn ($q, $c) => $q->where('city', $c))
            ->orderByRaw('case when status = ? then 0 else 1 end', ['ranked'])->orderByDesc('score')->limit((int) ($i['limit'] ?? 10))->get();

        return ['published' => true, 'published_at' => $calc->published_at?->toDateString(), 'methodology_version' => $calc->methodology_version,
            'includes_demo' => (bool) $calc->includes_demo, 'rows' => $rows->map(fn ($r) => $this->rankingRow($r))->all()];
    }

    protected function advisor(array $i): array
    {
        $a = app(InvestmentAdvisor::class)->answer($i['question'], $i['city'] ?? null, $i['segment'] ?? null, $i['focus'] ?? null, reword: false);

        return collect($a)->except('items')->all() + ['items' => collect($a['items'])->map(fn ($x) => [
            'key' => $x['key'], 'name' => $x['name'], 'location' => $x['location'], 'group' => $x['group_label'], 'score' => $x['score'], 'status' => $x['status_label'],
            'confidence' => $x['confidence_label'], 'yield' => $x['yield'], 'image' => $x['image'], 'demo' => $x['is_demo'],
        ])->all()];
    }

    /** The published Investment Index leaderboard of one category; never a score the engine did not publish. */
    protected function indexLeaderboard(string $category, int $limit): array
    {
        $run = InvestmentIndexRun::latestPublished();
        if (! $run) {
            return ['category' => config("investment_index.categories.$category.label"), 'published' => false, 'items' => [],
                'missing' => ['No Investment Index run has been published yet: Insufficient Verified Data.']];
        }
        $scores = $run->scores()->where('category', $category)->with('project:id,name,city,developer')->get();
        $ranked = $scores->where('status', 'ranked')->sortBy('category_rank')->take(max(1, min($limit, 25)));

        return [
            'category' => config("investment_index.categories.$category.label"), 'published' => true, 'run' => $run->id, 'published_at' => $run->published_at?->toDateString(),
            'methodology_version' => $run->methodology_version,
            'items' => $ranked->map(fn ($s) => ['rank' => $s->category_rank, 'name' => $s->project?->name, 'city' => $s->project?->city, 'developer' => $s->project?->developer,
                'score' => $s->score, 'overall_index_rank' => $s->overall_rank, 'confidence' => $s->confidence_score, 'confidence_level' => $s->confidence_level,
                'risk' => $s->riskLabel(), 'legal_status' => $s->legal_status, 'verified_yield' => $s->verified_yield, 'last_verified' => $s->last_verified_at?->toDateString()])->values()->all(),
            'counts' => ['assessed' => $scores->count(), 'ranked' => $scores->where('status', 'ranked')->count(), 'not_eligible' => $scores->where('status', 'not_eligible')->count(),
                'insufficient_verified_data' => $scores->where('status', 'insufficient')->count(), 'excluded' => $scores->where('status', 'excluded')->count()],
            'missing' => $ranked->isEmpty() ? ['No project in this category has enough verified evidence to be ranked: Insufficient Verified Data.'] : [],
        ];
    }

    protected function indexProject(string $name): array
    {
        $run = InvestmentIndexRun::latestPublished();
        $project = Project::where('is_demo', false)->where('name', 'like', '%'.addcslashes($name, '%_').'%')->orderByRaw('length(name)')->first();
        $score = $run && $project ? $run->scores()->where('project_id', $project->id)->first() : null;
        if (! $score) {
            return ['found' => false, 'missing' => [$project ? "{$project->name} has no published Investment Index assessment: Insufficient Verified Data." : "No real development named \"{$name}\" is in the Investment Index."]];
        }

        return ['found' => true, 'name' => $project->name, 'city' => $project->city, 'category' => $score->categoryLabel(), 'status' => $score->statusLabel(),
            'score' => $score->score, 'category_rank' => $score->category_rank, 'overall_index_rank' => $score->overall_rank, 'confidence' => $score->confidence_score,
            'confidence_level' => $score->confidence_level, 'risk' => $score->riskLabel(), 'risks' => $score->risks, 'legal_status' => $score->legal_status,
            'verified_yield' => $score->verified_yield, 'development_progress' => $score->development_progress, 'last_verified' => $score->last_verified_at?->toDateString(),
            'factors' => collect($score->factors)->map(fn ($f) => ['label' => $f['label'], 'weight' => $f['weight'], 'score' => $f['score'], 'value' => $f['value'], 'missing' => $f['missing']])->values()->all(),
            'reasons' => array_values(array_filter($score->reasons ?? [], fn ($r) => ! str_starts_with($r, 'Confidence:'))),
            'published_at' => $run->published_at?->toDateString(),
            'legal_records' => InvestmentLegalRecord::verified()->where('project_id', $project->id)->orderByDesc('issued_on')->limit(10)->get()
                ->map(fn ($l) => ['authority' => $l->authority, 'status' => $l->statusLabel(), 'date' => $l->issued_on?->toDateString(), 'reference' => $l->reference, 'source_url' => $l->source_url])->all(),
            'first_found_in' => $project->discoverySource?->name, 'first_found_url' => $project->discovery_url,
            // Read from a source but not yet checked by staff: never a fact, and in no score.
            'awaiting_staff_check' => InvestmentEvidence::where('project_id', $project->id)->where('verification_status', 'unverified')->with('source:id,name')->limit(10)->get()
                ->map(fn ($e) => ['factor' => $e->factor, 'value' => $e->value_numeric ?? $e->value_level, 'date' => $e->observed_on?->toDateString(), 'source' => $e->source?->name])->values()->all(),
            'stated_facts' => collect($project->research_facts ?? [])->take(10)->map(fn ($f) => ['label' => $f['label'], 'value' => $f['value'], 'source' => $f['source'], 'date' => $f['date']])->values()->all(),
            'changes' => InvestmentIndexChange::where('project_id', $project->id)->latest('id')->limit(5)->get()->flatMap(fn ($c) => $c->reasons ?? [])->values()->all()];
    }

    protected function rankingRow($r): array
    {
        return [
            'entity' => $r->entity_name, 'type' => $r->entity_type, 'city' => $r->city, 'asset_class' => $r->asset_class, 'category' => $r->category,
            'status' => $r->status, 'score' => $r->score, 'confidence' => $r->confidence, 'risk' => $r->risk,
            'change_1y' => $r->metric('change_1y.percent'), 'change_2y' => $r->metric('change_2y.percent'), 'gross_yield' => $r->metric('yield.gross'),
            'latest_price' => $r->metric('current.label'), 'price_basis' => $r->metric('current.basis'), 'legal' => $r->metric('legal.status'),
            'reason' => $r->factors['reason'] ?? null, 'demo' => (bool) $r->is_demo,
        ];
    }

    protected function compareInvestments(array $names, ?string $city = null): array
    {
        $calc = InvestmentCalculation::current();
        $out = [];
        foreach ($names as $name) {
            // Whole words from the start of a word: "DHA" must not match "Sargodha" or "Dharampura".
            $safe = str_replace(['%', '_'], ['\%', '\_'], $name);
            $named = fn (string $column) => fn ($q) => $q->where($column, $safe)->orWhere($column, 'like', $safe.' %')->orWhere($column, 'like', '% '.$safe)->orWhere($column, 'like', '% '.$safe.' %');
            $rows = $calc ? $calc->rankings()->where('category', 'overall')->where($named('entity_name'))->when($city, fn ($q) => $q->where('city', $city))->limit(3)->get() : collect();
            $areas = Area::active()->where($named('name'))->when($city, fn ($q) => $q->where('city', $city))->orderBy('depth')->limit(5)->get(['id', 'name', 'city', 'approval_status']);
            $areaIds = $areas->pluck('id');
            $out[] = ['query' => $name, 'found' => $rows->isNotEmpty(), 'rows' => $rows->map(fn ($r) => $this->rankingRow($r))->all(),
                'note' => $rows->isEmpty() ? ($calc ? 'No published investment data for this name.' : 'No investment rankings have been published yet.') : null,
                'locations' => $areas->map(fn ($a) => ['name' => $a->name, 'city' => $a->city, 'approval_status' => str_replace('_', ' ', (string) $a->approval_status)])->all(),
                'verified_observations' => $areaIds->isEmpty() ? 0 : MarketObservation::verified()->whereIn('area_id', $areaIds)->count(),
                'verified_indicators' => $areaIds->isEmpty() ? 0 : MarketIndicator::verified()->whereIn('area_id', $areaIds)->count()];
        }

        return ['published' => (bool) $calc, 'items' => $out];
    }

    protected function yield(array $i): array
    {
        return [
            'gross_yield_percent' => MarketMetrics::yieldPercent($i['monthly_rent'], $i['price']),
            'net_yield_percent' => isset($i['annual_costs']) ? MarketMetrics::netYieldPercent($i['monthly_rent'], $i['annual_costs'], $i['price']) : null,
            'formula' => 'monthly rent × 12 ÷ price × 100 (gross); net subtracts annual costs',
        ];
    }

    protected function marketQuality(): array
    {
        $status = fn (string $m) => $m::query()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
        $latest = InvestmentCalculation::latest('id')->first();

        return [
            'observations_by_status' => $status(MarketObservation::class),
            'indicators_by_status' => $status(MarketIndicator::class),
            'verified_observations_by_city' => MarketObservation::verified()->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city')->map(fn ($n) => (int) $n)->all(),
            'sources_by_reliability' => MarketSource::query()->toBase()->selectRaw('reliability, count(*) as n')->groupBy('reliability')->pluck('n', 'reliability')->map(fn ($n) => (int) $n)->all(),
            'asking_only_observations' => MarketObservation::verified()->where('price_basis', 'asking')->count(),
            'latest_calculation' => $latest ? ['id' => $latest->id, 'status' => $latest->status, 'date' => $latest->created_at?->toDateString()] : null,
        ];
    }

    protected function locationCoverage(): array
    {
        $count = fn ($q) => $q->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city');
        $areas = $count(Area::active());
        $centres = $count(Area::active()->whereNotNull('latitude'));
        $reviewed = $count(Area::active()->whereNotNull('verified_on'));
        $boundaries = AreaBoundary::published()->join('areas', 'areas.id', '=', 'area_boundaries.area_id')->toBase()->selectRaw('areas.city as city, count(*) as n')->groupBy('areas.city')->pluck('n', 'city');
        $projects = $count(Project::query());

        return ['countries' => collect(config('meezan.investment.markets'))->map(fn ($m) => $m['status'])->all(), 'cities' => collect(Cities::names())->mapWithKeys(fn ($c) => [$c => [
            'areas' => (int) ($areas[$c] ?? 0), 'with_centre' => (int) ($centres[$c] ?? 0), 'source_checked' => (int) ($reviewed[$c] ?? 0),
            'published_boundaries' => (int) ($boundaries[$c] ?? 0), 'projects' => (int) ($projects[$c] ?? 0),
        ]])->all()];
    }

    protected function locationReview(?string $city): array
    {
        $base = fn () => Area::active()->when($city, fn ($q) => $q->where('city', $city));
        $count = fn ($q) => $q->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city');
        $flagged = $count($base()->where('needs_review', true));
        $noCentre = $count($base()->whereNull('latitude'));
        $popular = $count($base()->popular()->whereNull('latitude'));
        $unverified = $count($base()->where('approval_status', 'unverified'));

        return [
            'cities' => collect($city ? [$city] : Cities::names())->mapWithKeys(fn ($c) => [$c => [
                'needs_review' => (int) ($flagged[$c] ?? 0), 'no_centre' => (int) ($noCentre[$c] ?? 0),
                'popular_no_centre' => (int) ($popular[$c] ?? 0), 'approval_unverified' => (int) ($unverified[$c] ?? 0),
            ]])->all(),
            'possible_duplicates' => $base()->toBase()->selectRaw('city, normalized_name, count(*) as n')->whereNotNull('normalized_name')->groupBy('city', 'normalized_name')->havingRaw('count(*) > 1')->limit(25)->get()
                ->map(fn ($d) => ['city' => $d->city, 'name' => $d->normalized_name, 'records' => (int) $d->n])->all(),
            'flagged_sample' => $base()->where('needs_review', true)->orderBy('city')->orderBy('id')->limit(15)->get(['id', 'city', 'name', 'notes'])
                ->map(fn ($a) => ['id' => $a->id, 'city' => $a->city, 'name' => $a->name, 'note' => mb_substr((string) $a->notes, 0, 140)])->all(),
        ];
    }

    protected function funnel(int $days): array
    {
        $since = $this->since($days);

        return [
            'period_days' => $days,
            'campaign_data' => 'Not connected: no advertising or analytics account is configured.',
            'new_listings_by_city' => Property::where('created_at', '>=', $since)->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city')->map(fn ($n) => (int) $n)->all(),
            'inquiries' => DB::table('inquiries')->where('created_at', '>=', $since)->count(),
            'leads_by_source' => DB::table('leads')->where('created_at', '>=', $since)->selectRaw('source, count(*) as n')->groupBy('source')->pluck('n', 'source')->map(fn ($n) => (int) $n)->all(),
            'new_dealer_signups' => User::where('role', 'dealer')->where('created_at', '>=', $since)->count(),
            'new_buyer_signups' => User::where('role', 'buyer')->where('created_at', '>=', $since)->count(),
        ];
    }

    protected function seoAudit(): array
    {
        $indexableCategories = 0;
        foreach (Cities::names() as $c) {
            foreach (Seo::categoryCounts($c) as $counts) {
                $indexableCategories += Seo::indexable($counts['real']) ? 1 : 0;
            }
        }
        $published = Property::query()->published()->where('is_demo', false);

        return [
            'search_console' => 'Not connected: no Google Search Console credentials are configured, so rankings and indexing data are unavailable.',
            'sitemap_listing_urls' => (clone $published)->count(),
            'indexable_city_category_pages' => $indexableCategories,
            'published_demo_listings_excluded' => Property::query()->published()->where('is_demo', true)->count(),
            'real_listings_without_photos' => (clone $published)->doesntHave('images')->count(),
            'real_listings_short_description' => (clone $published)->whereRaw('length(description) < 300')->count(),
            'popular_areas' => Area::active()->popular()->count(),
        ];
    }

    protected function health(): array
    {
        $log = storage_path('logs/laravel.log');
        $errors = [];
        if (File::exists($log)) {
            $tail = $this->tail($log, 400);
            preg_match_all('/^\[(\d{4}-\d{2}-\d{2}) [\d:]+\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.{0,160})/m', $tail, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                $msg = OutputGuard::text(preg_replace('/\(Connection: .*$/', '', $hit[3]));
                $errors[$msg] = ['message' => $msg, 'level' => $hit[2], 'last_seen' => $hit[1], 'count' => ($errors[$msg]['count'] ?? 0) + 1];
            }
        }

        $waiting = DB::table('jobs')->selectRaw('queue, count(*) as n')->groupBy('queue')->pluck('n', 'queue');
        $failed = DB::table('failed_jobs')->selectRaw('queue, count(*) as n')->groupBy('queue')->pluck('n', 'queue');
        $oldest = DB::table('jobs')->min('created_at');
        $migrator = app('migrator');
        $pending = $migrator->repositoryExists() ? count(array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan())) : null;

        return [
            'environment' => app()->environment(),
            'debug_mode' => (bool) config('app.debug'),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'queued_jobs' => DB::table('jobs')->count(),
            'queue_driver' => config('queue.default'),
            'config_cached' => app()->configurationIsCached(),
            'pending_migrations' => $pending,
            'oldest_queued_job_hours' => $oldest ? round((now()->timestamp - (int) $oldest) / 3600, 1) : null,
            'queues' => $waiting->keys()->merge($failed->keys())->unique()->mapWithKeys(fn ($q) => [$q => ['waiting' => (int) ($waiting[$q] ?? 0), 'failed' => (int) ($failed[$q] ?? 0)]])->all(),
            'scheduled_agent_jobs' => AiTask::where('source', 'schedule')->latest('id')->limit(20)->get(['request', 'status', 'created_at'])->unique('request')
                ->map(fn ($t) => ['job' => $t->request, 'last_run' => $t->created_at?->toDateTimeString(), 'status' => $t->status])->values()->all(),
            'recent_log_errors' => array_values(array_slice($errors, -10)),
            'failed_agent_tasks_24h' => AiTask::where('status', 'failed')->where('updated_at', '>=', now()->subDay())->count(),
            'note' => 'Read-only diagnostics. No commands, file changes or deployments are performed by agents.',
        ];
    }

    protected function tail(string $path, int $lines): string
    {
        $size = filesize($path);
        $h = fopen($path, 'r');
        fseek($h, max(0, $size - 200_000));
        $text = stream_get_contents($h);
        fclose($h);

        return implode("\n", array_slice(explode("\n", (string) $text), -$lines));
    }

    protected function aiUsage(int $days): array
    {
        $since = $this->since($days);
        $usage = AiUsageRecord::where('created_at', '>=', $since);

        return [
            'period_days' => $days,
            'tasks' => AiTask::where('created_at', '>=', $since)->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all(),
            'model_calls' => (clone $usage)->count(),
            'tokens_in' => (int) (clone $usage)->sum('tokens_in'),
            'tokens_out' => (int) (clone $usage)->sum('tokens_out'),
            'cost_usd' => (clone $usage)->whereNotNull('cost_usd')->exists() ? round((float) (clone $usage)->sum('cost_usd'), 4) : null,
            'by_provider' => (clone $usage)->toBase()->selectRaw('provider, count(*) as requests, sum(tokens_in + tokens_out) as tokens, sum(cost_usd) as cost')->groupBy('provider')->get()
                ->map(fn ($r) => ['provider' => $r->provider, 'requests' => (int) $r->requests, 'tokens' => (int) $r->tokens, 'cost_usd' => $r->cost === null ? null : round((float) $r->cost, 4)])->all(),
            'cost_note' => AiProvider::pricing() ? 'Cost from configured model pricing.' : 'No model pricing configured, so cost is not calculated.',
        ];
    }

    protected function staffDirectory(): array
    {
        $staff = User::where('role', 'admin')->get(['id', 'name', 'status', 'last_login_at']);
        $open = StaffTask::where('status', 'open')->toBase()->selectRaw('assigned_to, count(*) as n')->groupBy('assigned_to')->pluck('n', 'assigned_to');

        return ['staff' => $staff->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => 'Administrator', 'status' => $u->status, 'open_tasks' => (int) ($open[$u->id] ?? 0)])->all(),
            'unassigned_open_tasks' => (int) StaffTask::where('status', 'open')->whereNull('assigned_to')->count(),
            'note' => 'Only names, roles and task counts. No personal or employment records are held or read.'];
    }

    protected function ticket(array $i, AgentContext $ctx): array
    {
        $ticket = SupportTicket::create([
            'user_id' => $ctx->user?->id, 'task_id' => $ctx->task->id, 'conversation_id' => $ctx->task->input['conversation_id'] ?? null,
            'channel' => $ctx->task->source, 'category' => $i['category'], 'subject' => $i['subject'], 'summary' => OutputGuard::text($i['summary']), 'priority' => $i['priority'] ?? 'normal',
        ]);

        AgentAlerts::ticketOpened($ticket);

        return ['ticket' => $ticket->reference, 'status' => 'open'];
    }

    protected function staffTask(array $i, AgentContext $ctx): array
    {
        // One open task per chat, however many times the customer asks.
        if (($i['category'] ?? null) === 'dealer_interest' && ($open = StaffTask::where('category', 'dealer_interest')->where('related_type', $i['related_type'] ?? null)->where('related_id', $i['related_id'] ?? null)->where('status', 'open')->first())) {
            return ['staff_task_id' => $open->id, 'status' => 'open'];
        }
        $task = StaffTask::create([
            'title' => $i['title'], 'description' => $i['description'], 'category' => $i['category'], 'related_type' => $i['related_type'] ?? null, 'related_id' => $i['related_id'] ?? null,
            'due_on' => isset($i['due_in_days']) ? now()->addDays($i['due_in_days'])->toDateString() : null, 'status' => 'open', 'agent_id' => $ctx->agent->id,
        ]);

        return ['staff_task_id' => $task->id, 'status' => 'open'];
    }
}
