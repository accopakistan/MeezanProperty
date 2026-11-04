<?php

namespace App\Services\Investment;

use App\Enums\PropertyType;
use App\Models\Area;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentRanking;
use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use App\Models\Project;
use App\Models\Property;
use App\Support\Cities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read side of Investment Rankings (city → group → segment). Everything
 * comes from the published ranking snapshot and the live tables: counts are
 * real counts, and published projects with no verified market data are
 * listed as "Insufficient Data" instead of being scored or left out.
 *
 * Display status (no new scoring, only how a snapshot row is labelled):
 *   ranked        scored with medium or high data confidence
 *   provisional   scored, but on limited data
 *   review        not scored; market records are waiting for review
 *   insufficient  not enough verified data to score
 */
class RankingBrowser
{
    public const STATUS_LABELS = ['ranked' => 'Ranked', 'provisional' => 'Provisional', 'review' => 'Under Review', 'insufficient' => 'Insufficient Data'];

    public const PROJECT_STAGES = ['completed' => 'Operational', 'under_construction' => 'Under Construction', 'upcoming' => 'Upcoming'];

    /** City-page tabs that are lists, not segments. */
    public const SPECIAL_TABS = ['all' => 'All Investments', 'highest-yield' => 'Highest Rental Yield', 'fastest-growing' => 'Fastest Growing', 'new-upcoming' => 'New & Upcoming'];

    protected ?InvestmentCalculation $calc = null;

    protected bool $loaded = false;

    public function calculation(): ?InvestmentCalculation
    {
        if (! $this->loaded) {
            $this->calc = InvestmentCalculation::current();
            $this->loaded = true;
        }

        return $this->calc;
    }

    /** Segment definitions with every optional key present. */
    public static function segments(): array
    {
        $defaults = ['area_types' => null, 'project_types' => [], 'types' => null, 'group_by' => null, 'sizes' => [], 'bedrooms' => [], 'columns' => []];

        return array_map(fn ($s) => $s + $defaults, config('meezan.investment.segments'));
    }

    public static function segment(string $slug): ?array
    {
        return static::segments()[$slug] ?? null;
    }

    /** @return array<string, array> segments of one group */
    public static function segmentsIn(string $group): array
    {
        return array_filter(static::segments(), fn ($s) => $s['group'] === $group);
    }

    /** "lahore" → "Lahore", "pakistan" → null (all cities); false when unknown. */
    public static function cityFromSlug(string $slug): string|false|null
    {
        if ($slug === 'pakistan') {
            return null;
        }

        return Cities::fromSlug($slug) ?? false;
    }

    public static function citySlug(?string $city): string
    {
        return $city ? Cities::slug($city) : 'pakistan';
    }

    /** Project categories that belong to a group (for counts and upcoming lists). */
    public static function projectCategoriesFor(?string $group = null, ?string $segment = null): array
    {
        if ($segment) {
            return static::segment($segment)['project_types'] ?? [];
        }

        return collect(static::segments())->when($group, fn ($c) => $c->where('group', $group))->pluck('project_types')->flatten()->unique()->values()->all();
    }

    /** Published projects; demo projects only while demo mode is on. */
    public function projects(): Builder
    {
        return Project::published()->when(! InvestmentCalculation::demoMode(), fn ($q) => $q->where('is_demo', false));
    }

    /** True when the snapshot on show contains demonstration data. */
    public function showsDemo(): bool
    {
        return (bool) $this->calculation()?->includes_demo;
    }

    /* Cities and overview counts ------------------------------------------ */

    /** City cards: real counts per city. */
    public function cities(?string $segment = null): array
    {
        $societyTypes = static::segment('societies')['area_types'];
        $societies = Area::active()->whereIn('type', $societyTypes)->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city');
        $commercial = $this->projects()->whereIn('category', static::projectCategoriesFor('commercial'))->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city');
        $listings = Property::published()->where('is_demo', false)->where('purpose', 'sale')->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city');
        $tracked = $this->calculation()
            ? $this->calculation()->rankings()->whereNotNull('segment')->when($segment, fn ($q) => $q->where('segment', $segment))
                ->get(['city', 'entity_type', 'entity_id'])->groupBy('city')->map(fn ($g) => $g->unique(fn ($r) => $r->entity_type.':'.$r->entity_id)->count())
            : collect();
        $segmentProjects = $segment ? $this->projects()->whereIn('category', static::projectCategoriesFor(segment: $segment))
            ->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city') : collect();

        return collect(Cities::names())->map(fn ($city) => [
            'name' => $city,
            'slug' => Cities::slug($city),
            'photo' => config('meezan.place_photos.'.$city),
            'societies' => (int) ($societies[$city] ?? 0),
            'commercial_projects' => (int) ($commercial[$city] ?? 0),
            'listings' => (int) ($listings[$city] ?? 0),
            'tracked' => (int) ($tracked[$city] ?? 0),
            'matching' => $segment ? max((int) ($tracked[$city] ?? 0), (int) ($segmentProjects[$city] ?? 0)) : null,
        ])->all();
    }

    /** The overview figures for one city (or all, when null). */
    public function overview(?string $city): array
    {
        $listings = Property::published()->where('is_demo', false)->when($city, fn ($q) => $q->where('city', $city));
        $byType = (clone $listings)->toBase()->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');
        $typeCount = fn (array $types) => (int) collect($types)->sum(fn ($t) => $byType[$t] ?? 0);
        $projects = $this->projects()->when($city, fn ($q) => $q->where('city', $city))->toBase()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category');
        $projectCount = fn (array $cats) => (int) collect($cats)->sum(fn ($c) => $projects[$c] ?? 0);

        $rows = $this->snapshotRows($city, 'overall');

        return [
            'listings' => (int) $byType->sum(),
            'societies' => Area::active()->whereIn('type', static::segment('societies')['area_types'])->when($city, fn ($q) => $q->where('city', $city))->count(),
            'commercial_projects' => $projectCount(static::projectCategoriesFor('commercial')),
            'projects' => (int) $projects->sum(),
            'plots' => $typeCount(array_map(fn ($t) => $t->value, PropertyType::inGroup('plots'))),
            'homes' => $typeCount(array_map(fn ($t) => $t->value, PropertyType::inGroup('homes'))),
            'shopping_malls' => $projectCount(['shopping_mall']),
            'commercial_plazas' => $projectCount(['commercial_plaza']),
            'business_towers' => $projectCount(['business_tower', 'office_building']),
            'verified_observations' => MarketObservation::verified()->when($city, fn ($q) => $q->where('city', $city))->count(),
            'verified_indicators' => MarketIndicator::verified()->when($city, fn ($q) => $q->where('city', $city))->count(),
            'ranked' => $rows->where('status', 'ranked')->count(),
            'highest_yield' => $rows->filter(fn ($r) => $r->metric('yield.gross') !== null)->sortByDesc(fn ($r) => $r->metric('yield.gross'))->take(3)->values(),
            'fastest_growing' => $rows->filter(fn ($r) => $r->metric('annualised') !== null)->sortByDesc(fn ($r) => $r->metric('annualised'))->take(3)->values(),
            'upcoming' => $this->upcoming($city)->count(),
            'demo_records' => $this->showsDemo() ? MarketObservation::where('is_demo', true)->when($city, fn ($q) => $q->where('city', $city))->count()
                + MarketIndicator::where('is_demo', true)->when($city, fn ($q) => $q->where('city', $city))->count() : 0,
        ];
    }

    /* Rankings -------------------------------------------------------------- */

    /** Snapshot rows: one ranking category (city page) or one segment. */
    public function snapshotRows(?string $city, ?string $category = null, ?string $segment = null): Collection
    {
        $calc = $this->calculation();
        if (! $calc) {
            return collect();
        }

        return $calc->rankings()
            ->when($segment, fn ($q) => $q->where('segment', $segment), fn ($q) => $q->whereNull('segment')->where('category', $category ?? 'overall'))
            ->when($city, fn ($q) => $q->where('city', $city))
            ->get();
    }

    /**
     * The display items of a segment in a city: snapshot rows, then published
     * projects of the segment's categories that have no row yet.
     *
     * @return Collection<int, array>
     */
    public function items(string $segment, ?string $city, array $filters = []): Collection
    {
        $seg = static::segment($segment);
        $rows = $this->snapshotRows($city, segment: $segment);
        $projects = $this->projects()->with('area:id,name,city,latitude,longitude')
            ->when($city, fn ($q) => $q->where('city', $city))
            ->where(fn ($q) => $q->whereIn('category', $seg['project_types'] ?: ['__none__'])->orWhereIn('id', $rows->where('entity_type', 'project')->pluck('entity_id')))
            ->get()->keyBy('id');
        $areas = Area::whereIn('id', $rows->where('entity_type', 'area')->pluck('entity_id'))->get(['id', 'name', 'city', 'type', 'latitude', 'longitude', 'parent_id'])->keyBy('id');

        $items = $rows->map(fn (InvestmentRanking $r) => $this->item($r, $r->entity_type === 'project' ? $projects->get($r->entity_id) : null, $r->entity_type === 'area' ? $areas->get($r->entity_id) : null, $seg));

        // Projects in this segment without verified data: listed, never scored.
        if (empty($seg['group_by'])) {
            $withRow = $rows->where('entity_type', 'project')->pluck('entity_id')->all();
            $pending = $this->pendingProjects();
            $items = $items->concat($projects->filter(fn ($p) => in_array($p->category, $seg['project_types'], true) && ! in_array($p->id, $withRow, true))
                ->map(fn (Project $p) => $this->item(null, $p, null, $seg, isset($pending[$p->id]))));
        }

        return $this->filter($items, $filters, $seg)->values();
    }

    /** One table / card entry. */
    public function item(?InvestmentRanking $r, ?Project $project, ?Area $area, array $seg, bool $underReview = false): array
    {
        $status = match (true) {
            $r?->status === 'ranked' && in_array($r->confidence, ['high', 'medium'], true) => 'ranked',
            $r?->status === 'ranked' => 'provisional',
            $underReview => 'review',
            default => 'insufficient',
        };
        $metric = fn (string $k) => $r?->metric($k);
        $coords = $metric('coordinates') ?? match (true) {
            $project?->hasCoordinates() => [$project->latitude, $project->longitude],
            $project?->area?->hasCoordinates() => [(float) $project->area->latitude, (float) $project->area->longitude],
            $area?->hasCoordinates() => [(float) $area->latitude, (float) $area->longitude],
            default => null,
        };
        $type = $r?->entity_type ?? 'project';
        $id = $r?->entity_id ?? $project?->id;

        return [
            'key' => implode(':', array_filter([$type, $id, $r?->size_key])),
            'row' => $r,
            'project' => $project,
            'name' => $r?->entity_name ?? $project?->name,
            'city' => $r?->city ?? $project?->city,
            // A project is placed by its area; an area (society) by its city.
            'location' => $project ? ($project->area?->name ?? $project->city) : ($area?->city ?? $r?->city),
            'group_label' => $metric('group_label'),
            'size_key' => $r?->size_key,
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'score' => $status === 'ranked' || $status === 'provisional' ? $r->score : null,
            'rank' => $r?->rank,
            'confidence' => $r?->confidence ?? 'insufficient',
            'confidence_label' => $r?->confidenceLabel() ?? InvestmentRanking::CONFIDENCE['insufficient'],
            'risk' => $r?->risk ?? 'insufficient',
            'risk_label' => $r?->riskLabel() ?? InvestmentRanking::RISK['insufficient'],
            'price' => $metric('current'),
            'change_1y' => $metric('change_1y.percent'),
            'change_2y' => $metric('change_2y.percent'),
            'change_5y' => $metric('change_5y.percent'),
            'yield' => $metric('yield.gross'),
            'occupancy' => $metric('occupancy'),
            'development' => $metric('indicators.development_progress.value'),
            'stage' => $project?->status,
            'stage_label' => $project ? (self::PROJECT_STAGES[$project->status] ?? $project->statusLabel()) : null,
            'image' => $project?->cover_path ? $project->coverUrl() : null,
            'coordinates' => $coords,
            'reason' => $r && $status === 'insufficient' ? ($r->factors['reason'] ?? null) : null,
            'is_demo' => (bool) ($r?->is_demo || $project?->is_demo),
            'featured' => (bool) $project?->is_featured,
        ];
    }

    protected function filter(Collection $items, array $f, array $seg): Collection
    {
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')));
        $items = $items
            ->when($q !== '', fn ($c) => $c->filter(fn ($i) => str_contains(mb_strtolower($i['name'].' '.$i['location']), $q)))
            ->when($f['size'] ?? null, fn ($c, $size) => $c->where('size_key', $size))
            ->when($f['stage'] ?? null, fn ($c, $stage) => $c->where('stage', $stage))
            ->when($f['risk'] ?? null, fn ($c, $risk) => $c->where('risk', $risk))
            ->when($f['confidence'] ?? null, fn ($c, $conf) => $c->filter(fn ($i) => in_array($i['confidence'], $conf === 'medium' ? ['high', 'medium'] : [$conf], true)))
            ->when(isset($f['min_yield']), fn ($c) => $c->filter(fn ($i) => $i['yield'] !== null && $i['yield'] >= (float) $f['min_yield']))
            ->when(isset($f['budget']), fn ($c) => $c->filter(fn ($i) => ($i['price']['amount'] ?? null) !== null && $i['price']['amount'] <= (int) $f['budget']));

        $sort = $f['sort'] ?? 'score';
        $value = fn ($i) => match ($sort) {
            'price' => $i['price']['amount'] ?? null,
            'yield' => $i['yield'],
            'change' => $i['change_1y'],
            'name' => $i['name'],
            default => $i['score'],
        };
        $desc = ($f['dir'] ?? ($sort === 'name' || $sort === 'price' ? 'asc' : 'desc')) === 'desc';
        $order = ['ranked' => 0, 'provisional' => 1, 'review' => 2, 'insufficient' => 3];

        // Entries without the value go last whatever the direction; then status, then name.
        return $items->sort(function ($a, $b) use ($value, $desc, $order) {
            [$va, $vb] = [$value($a), $value($b)];
            if (($va === null) !== ($vb === null)) {
                return $va === null ? 1 : -1;
            }
            if ($va !== null && $va != $vb) {
                return $desc ? $vb <=> $va : $va <=> $vb;
            }

            return [$order[$a['status']], $a['name']] <=> [$order[$b['status']], $b['name']];
        });
    }

    /** Project ids with market records submitted or reviewed but not yet verified. */
    protected function pendingProjects(): array
    {
        return MarketObservation::whereIn('status', ['submitted', 'reviewed'])->whereNotNull('project_id')->pluck('project_id')
            ->merge(MarketIndicator::whereIn('status', ['submitted', 'reviewed'])->whereNotNull('project_id')->pluck('project_id'))
            ->unique()->flip()->all();
    }

    /* Projects -------------------------------------------------------------- */

    /** New, upcoming and under-construction projects (newest launches first). */
    public function upcoming(?string $city, ?array $categories = null, int $limit = 8): Collection
    {
        return $this->projects()->with('area:id,name')
            ->when($city, fn ($q) => $q->where('city', $city))
            ->when($categories !== null, fn ($q) => $q->whereIn('category', $categories ?: ['__none__']))
            ->where(fn ($q) => $q->whereIn('status', ['upcoming', 'under_construction'])->orWhere('launched_on', '>=', now()->subYear()))
            ->orderByRaw('launched_on is null')->orderByDesc('launched_on')->orderBy('name')
            ->limit($limit)->get();
    }

    /* Map ------------------------------------------------------------------- */

    /** Markers for items with a known position (exact for projects, area centre otherwise). */
    public function markers(Collection $items, callable $url): array
    {
        return $items->filter(fn ($i) => $i['coordinates'])->unique(fn ($i) => $i['key'])->map(fn ($i) => [
            'key' => $i['key'],
            'lat' => (float) $i['coordinates'][0],
            'lng' => (float) $i['coordinates'][1],
            'name' => $i['name'],
            'location' => $i['location'],
            'status' => $i['status'],
            'status_label' => $i['status_label'],
            'score' => $i['score'],
            'price' => $i['price']['label'] ?? null,
            'yield' => $i['yield'],
            'approximate' => $i['project'] === null || ! $i['project']->hasCoordinates(),
            'demo' => $i['is_demo'],
            'url' => $url($i),
        ])->values()->all();
    }
}
