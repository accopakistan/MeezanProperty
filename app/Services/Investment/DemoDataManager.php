<?php

namespace App\Services\Investment;

use App\Models\Area;
use App\Models\City;
use App\Models\InvestmentCalculation;
use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Cities;
use Illuminate\Support\Facades\DB;

/**
 * Demonstration dataset for Investment Rankings: fictional projects in real
 * localities of the seven cities (names read naturally; is_demo and the page
 * notices say they are illustrative), with
 * illustrative price, rent and indicator records. Everything is flagged
 * is_demo, demo records carry the status "demo" (never "verified"), and they
 * are ranked only in a separate demo calculation by the normal scoring engine.
 *
 * Deterministic and idempotent: each project's figures come from a random
 * generator seeded with its name, so seeding again finds the same projects
 * and creates nothing twice. Real (non-demo) records are never changed or
 * removed; a real project with the same name and city is left alone.
 */
class DemoDataManager
{
    public const METHODOLOGY = 'Illustrative figure generated for platform preview (demo data); not market data.';

    /** Suffix used by the first version of the dataset; recognised so those records are renamed, not duplicated. */
    public const LEGACY_SUFFIX = ' (Demo)';

    public const DESCRIPTION = 'Illustrative example project — fictional, created to preview the Investment Rankings. It does not exist; its prices, rents, yields, scores and forecasts are illustrative and not verified market information.';

    /** Kind of demo project → [project category, count per city, illustration]. */
    public const PLAN = [
        'society' => ['residential_society', 10, 'society'],
        'apartments' => ['apartment_complex', 8, 'apartments'],
        'mall' => ['shopping_mall', 8, 'mall'],
        'plaza' => ['commercial_plaza', 8, 'plaza'],
        'tower' => ['business_tower', 8, 'tower'],
        'land' => ['commercial_land', 8, 'land'],
        'mixed' => ['mixed_use', 5, 'mixed'],
        'upcoming' => [null, 5, null],
    ];

    /** Real localities used for names and positions (the projects themselves are fictional). */
    public const LOCALITIES = [
        'Lahore' => ['Gulberg', 'DHA Phase 6', 'Johar Town', 'Bahria Town', 'Model Town', 'Wapda Town', 'Valencia Town', 'Lake City', 'Faisal Town', 'Garden Town', 'Raiwind Road', 'Bedian Road', 'Iqbal Town', 'Cantt', 'Ferozepur Road'],
        'Karachi' => ['Clifton', 'DHA Phase 8', 'Gulshan-e-Iqbal', 'North Nazimabad', 'PECHS', 'Bahria Town Karachi', 'Scheme 33', 'Gulistan-e-Jauhar', 'Saddar', 'Shahrah-e-Faisal', 'Tariq Road', 'Federal B Area', 'Malir', 'Korangi', 'Defence View'],
        'Islamabad' => ['F-7', 'F-10', 'G-11', 'E-11', 'DHA Islamabad', 'Bahria Town Islamabad', 'Blue Area', 'I-8', 'G-13', 'B-17', 'Gulberg Greens', 'Park Road', 'Kashmir Highway', 'D-12', 'H-13'],
        'Multan' => ['Bosan Road', 'Gulgasht Colony', 'Multan Cantt', 'DHA Multan', 'Shah Rukn-e-Alam Colony', 'Wapda Town', 'Northern Bypass', 'Mumtazabad', 'New Multan', 'Model Town', 'Khanewal Road', 'Vehari Road'],
        'Faisalabad' => ['D Ground', 'Peoples Colony', 'Madina Town', 'Canal Road', 'Satiana Road', 'Jaranwala Road', 'Kohinoor City', 'Susan Road', 'Gulberg', 'Sargodha Road', 'Jhang Road', 'Millat Town'],
        'Quetta' => ['Jinnah Town', 'Samungli Road', 'Airport Road', 'Zarghoon Road', 'Quetta Cantt', 'Chaman Housing Scheme', 'Shahbaz Town', 'Brewery Road', 'Satellite Town', 'Spinny Road', 'Sariab Road', 'Hazar Ganji'],
        'Peshawar' => ['Hayatabad', 'University Town', 'Saddar', 'Ring Road', 'DHA Peshawar', 'Regi Model Town', 'Warsak Road', 'Gulbahar', 'Charsadda Road', 'Kohat Road', 'Dalazak Road', 'Board Bazaar'],
    ];

    protected const NAMES = [
        'society' => ['Greens Residencia', 'Garden Enclave', 'Meadow Homes', 'Crescent Villas', 'Palm Residency', 'Riverside Estate', 'Orchard Homes', 'Royal Avenue', 'Sunrise Gardens', 'Lakeview Residency'],
        'apartments' => ['Heights Apartments', 'Skyline Residences', 'Elite Towers', 'Park View Apartments', 'Crystal Residences', 'City Lofts', 'Horizon Apartments', 'Grand Residences'],
        'mall' => ['Galleria Mall', 'Grand Mall', 'City Centre Mall', 'Mega Mall', 'Emporium Square', 'Shopping Arcade', 'Fashion Mall', 'Retail Galleria'],
        'plaza' => ['Trade Plaza', 'Business Plaza', 'Commerce Plaza', 'Al-Noor Plaza', 'Capital Plaza', 'Market Plaza', 'Corner Plaza', 'Central Plaza'],
        'tower' => ['Business Tower', 'Corporate Centre', 'Executive Tower', 'Financial Tower', 'Trade Tower', 'Enterprise House', 'Signature Tower', 'Summit Tower'],
        'land' => ['Commerce District', 'Business Boulevard', 'Commercial Avenue', 'Trade Zone', 'Enterprise Park', 'Market District', 'Commercial Hub', 'Gateway Commercial'],
        'mixed' => ['Square Mixed-Use', 'One Mixed-Use', 'Central Mixed-Use', 'Boulevard Mixed-Use', 'Plaza Mixed-Use'],
        'upcoming' => ['New Horizons', 'Future City', 'Emerald Phase', 'Skyview', 'Pearl Development'],
    ];

    protected const DEVELOPERS = ['Crescent Developers', 'Indus Builders', 'Ravi Estates', 'Margalla Group', 'Sapphire Developments', 'Noor Builders', 'Kohinoor Associates', 'Arabian Sea Builders'];

    /** Price level of each city relative to Lahore (illustrative). */
    protected const CITY_LEVEL = ['Lahore' => 1.0, 'Karachi' => 1.05, 'Islamabad' => 1.3, 'Multan' => 0.55, 'Faisalabad' => 0.5, 'Quetta' => 0.45, 'Peshawar' => 0.6];

    protected const UPCOMING_KINDS = ['society', 'apartments', 'mall', 'tower', 'mixed'];

    /** @var array<string, MarketSource> */
    protected array $sources = [];

    protected array $created = ['projects' => 0, 'renamed' => 0, 'observations' => 0, 'indicators' => 0, 'skipped_real' => 0];

    /**
     * Create (or, with $refresh, regenerate) the demo dataset.
     *
     * @param  list<string>|null  $cities  null = all seven
     * @param  float  $scale  share of the per-city counts (tests use a small share)
     */
    public function seed(?array $cities = null, float $scale = 1.0, bool $refresh = false): array
    {
        $cities ??= Cities::names();
        $this->created = ['projects' => 0, 'renamed' => 0, 'observations' => 0, 'indicators' => 0, 'skipped_real' => 0];

        DB::transaction(function () use ($cities, $scale, $refresh) {
            $this->sources();
            foreach ($cities as $city) {
                foreach (self::PLAN as $kind => [$category, $count]) {
                    $n = max(1, (int) round($count * $scale));
                    for ($i = 0; $i < $n; $i++) {
                        $this->project($city, $kind, $i, $refresh);
                    }
                }
            }
        });
        AuditLogger::record('investment.demo_seeded', null, $this->created + ['cities' => $cities, 'refresh' => $refresh]);

        return $this->created;
    }

    /** Rank the demo dataset with the normal engine and publish it as the demo snapshot. */
    public function recalculate(?User $user = null): InvestmentCalculation
    {
        $calculator = app(RankingCalculator::class);
        $calc = $calculator->run($user, 'demo', includeDemo: true);
        if ($calc->status === 'completed') {
            $calculator->publish($calc, $user ?? User::where('role', 'admin')->first() ?? new User(['name' => 'System']));
        }

        return $calc->fresh();
    }

    /**
     * Remove every demo record. Real records are untouched; a demo project or
     * source still referenced by a real record is kept and reported.
     */
    public function remove(?User $user = null): array
    {
        $result = DB::transaction(function () {
            $calcs = InvestmentCalculation::where('includes_demo', true)->pluck('id');
            DB::table('investment_rankings')->whereIn('calculation_id', $calcs)->delete();
            DB::table('investment_calculations')->whereIn('id', $calcs)->delete();
            $observations = MarketObservation::where('is_demo', true)->delete();
            $indicators = MarketIndicator::where('is_demo', true)->delete();

            $usedByReal = fn (string $column) => MarketObservation::where('is_demo', false)->whereNotNull($column)->pluck($column)
                ->merge(MarketIndicator::where('is_demo', false)->whereNotNull($column)->pluck($column))->unique();
            $keptProjects = Project::where('is_demo', true)->whereIn('id', $usedByReal('project_id'))->count();
            $projects = Project::where('is_demo', true)->whereNotIn('id', $usedByReal('project_id'))->delete();
            $keptSources = MarketSource::where('is_demo', true)->whereIn('id', $usedByReal('source_id'))->count();
            $sources = MarketSource::where('is_demo', true)->whereNotIn('id', $usedByReal('source_id'))->delete();

            return compact('projects', 'observations', 'indicators', 'sources', 'keptProjects', 'keptSources') + ['calculations' => $calcs->count()];
        });
        AuditLogger::record('investment.demo_removed', null, $result, $user?->id);

        return $result;
    }

    /** Counts for the Demo Data screen (demo and real kept apart). */
    public function stats(): array
    {
        $byCity = fn ($q) => $q->toBase()->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city')->map(fn ($n) => (int) $n);
        $demoCalc = InvestmentCalculation::where('includes_demo', true)->where('status', 'published')->latest('id')->first();
        $realCalc = InvestmentCalculation::where('includes_demo', false)->where('status', 'published')->latest('id')->first();

        return [
            'projects' => Project::where('is_demo', true)->count(),
            'observations' => MarketObservation::where('is_demo', true)->count(),
            'indicators' => MarketIndicator::where('is_demo', true)->count(),
            'sources' => MarketSource::where('is_demo', true)->count(),
            'projects_by_city' => $byCity(Project::where('is_demo', true)),
            'records_by_city' => $byCity(MarketObservation::where('is_demo', true)),
            'projects_by_category' => Project::where('is_demo', true)->toBase()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category')->map(fn ($n) => (int) $n),
            'demo_rows_by_segment' => $demoCalc ? $demoCalc->rankings()->whereNotNull('segment')->toBase()->selectRaw('segment, count(*) as n')->groupBy('segment')->pluck('n', 'segment')->map(fn ($n) => (int) $n) : collect(),
            'real' => [
                'projects' => Project::where('is_demo', false)->count(),
                'verified_observations' => MarketObservation::verified()->where('is_demo', false)->count(),
                'verified_indicators' => MarketIndicator::verified()->where('is_demo', false)->count(),
                'sources' => MarketSource::where('is_demo', false)->count(),
            ],
            'demo_calculation' => $demoCalc,
            'real_calculation' => $realCalc,
            'demo_mode' => InvestmentCalculation::demoMode(),
        ];
    }

    /* Building the dataset -------------------------------------------------- */

    protected function sources(): void
    {
        foreach ([
            'transactions' => ['Meezan Illustrative Dataset — transactions (not real)', 'high', 'Meezan Demo Dataset — transactions (illustrative, not real)'],
            'asking' => ['Meezan Illustrative Dataset — asking prices (not real)', 'medium', 'Meezan Demo Dataset — asking prices (illustrative, not real)'],
            'rents' => ['Meezan Illustrative Dataset — rental survey (not real)', 'medium', 'Meezan Demo Dataset — rental survey (illustrative, not real)'],
        ] as $key => [$name, $reliability, $legacy]) {
            $source = MarketSource::where('is_demo', true)->whereIn('name', [$name, $legacy])->first() ?? new MarketSource;
            $source->name = $name;
            $source->fill(['type' => 'demo', 'reliability' => $reliability, 'is_active' => true, 'coverage' => 'Seven launch cities (demonstration only)',
                'notes' => 'Demonstration source for the Investment Rankings preview. Contains no real market data.']);
            $source->forceFill(['is_demo' => true])->save();
            $this->sources[$key] = $source;
        }
    }

    protected function project(string $city, string $kind, int $i, bool $refresh): void
    {
        $realKind = $kind === 'upcoming' ? self::UPCOMING_KINDS[$i % count(self::UPCOMING_KINDS)] : $kind;
        $category = self::PLAN[$realKind][0];
        $localities = self::LOCALITIES[$city];
        $offset = array_search($kind, array_keys(self::PLAN), true) * 3;
        $locality = $localities[($i + $offset) % count($localities)];
        $name = $locality.' '.self::NAMES[$kind][$i % count(self::NAMES[$kind])];

        if (Project::where('city', $city)->where('name', $name)->where('is_demo', false)->exists()) {
            $this->created['skipped_real']++;

            return;
        }

        // The generator is seeded with the original record key (name with the legacy suffix), so
        // renaming the display name never changes a project's figures, status or position.
        $seedKey = $name.self::LEGACY_SUFFIX;
        mt_srand(crc32($city.'|'.$seedKey));
        $rand = fn (float $a, float $b) => $a + mt_rand() / mt_getrandmax() * ($b - $a);
        $status = $kind === 'upcoming' ? ($i % 2 ? 'under_construction' : 'upcoming') : ($rand(0, 1) < 0.2 ? 'under_construction' : 'completed');

        // Earlier versions added " (Demo)" to the name: rename that record instead of creating a second one.
        $project = Project::where('city', $city)->where('name', $name)->where('is_demo', true)->first()
            ?? Project::where('city', $city)->where('name', $name.self::LEGACY_SUFFIX)->where('is_demo', true)->first();
        $isNew = $project === null;
        $project ??= new Project(['city' => $city]);
        if ($project->exists && $project->name !== $name) {
            $this->created['renamed']++;
        }
        $project->name = $name;
        [$lat, $lng, $areaId] = $this->position($city, $locality, $seedKey);
        $illustration = self::PLAN[$realKind][2];
        $variant = ($i % 4) + 1;

        $project->fill([
            'developer' => self::DEVELOPERS[crc32($seedKey) % count(self::DEVELOPERS)].' (fictional)',
            'category' => $category, 'area_id' => $areaId, 'latitude' => $lat, 'longitude' => $lng, 'status' => $status,
            'launched_on' => $status === 'completed' ? null : now()->subMonths((int) $rand(1, 10))->toDateString(),
            'description' => self::DESCRIPTION.' Located (fictionally) in '.$locality.', '.$city.'.',
            'approval_note' => 'Illustrative record — no real approval exists.',
            'approval_status' => match ($status) {
                'completed' => $rand(0, 1) < 0.7 ? 'approved' : 'noc_issued', 'under_construction' => 'noc_issued', default => 'under_process'
            },
            'specifications' => $this->specifications($realKind, $rand),
        ]);
        $project->forceFill([
            'is_published' => true, 'is_demo' => true, 'is_featured' => $i < 2 && $kind !== 'upcoming',
            'cover_path' => "images/demo/investments/{$illustration}-{$variant}.svg",
            'gallery' => collect($this->galleryTypes($realKind))->map(fn ($t, $n) => "images/demo/investments/{$t}-".((($variant + $n) % 4) + 1).'.svg')->all(),
        ])->save();
        $this->created['projects'] += $isNew ? 1 : 0;

        $hasRecords = MarketObservation::where('project_id', $project->id)->where('is_demo', true)->exists();
        if ($hasRecords && ! $refresh) {
            return;
        }
        MarketObservation::where('project_id', $project->id)->where('is_demo', true)->delete();
        MarketIndicator::where('project_id', $project->id)->where('is_demo', true)->delete();
        $this->records($project, $realKind, $kind === 'upcoming', $i, $rand);
    }

    /** A real locality's centre when the directory has it; otherwise near the city centre. Each project gets its own spot. */
    protected function position(string $city, string $locality, string $name): array
    {
        $area = Area::where('city', $city)->where('name', $locality)->orderByRaw('latitude is null')->first()
            ?? Area::where('city', $city)->where('name', 'like', $locality.'%')->orderByRaw('latitude is null')->first()
            ?? Area::where('city', $city)->where('name', 'like', '%'.$locality.'%')->orderByRaw('latitude is null')->first();
        $located = $area?->latitude !== null && $area?->longitude !== null;
        $centre = $located ? [(float) $area->latitude, (float) $area->longitude] : (City::centre($city) ?? config('meezan.map.center'));
        $spread = $located ? 0.012 : 0.07;
        $h = crc32($name);

        return [round($centre[0] + ((($h % 1000) / 1000) - 0.5) * $spread * 2, 7), round($centre[1] + (((intdiv($h, 1000) % 1000) / 1000) - 0.5) * $spread * 2, 7), $area?->id];
    }

    protected function records(Project $p, string $kind, bool $early, int $i, callable $rand): void
    {
        $level = self::CITY_LEVEL[$p->city] ?? 1.0;
        $commercial = in_array($kind, ['mall', 'plaza', 'tower', 'land'], true);
        $asset = $commercial ? 'commercial' : 'residential';

        // [property type, size, unit, bedrooms, base value (PKR), yearly growth range, gross yield range or null]
        $series = match ($kind) {
            'society' => array_values(array_filter([
                ['residential_plot', 5, 'marla', null, 9_000_000, [5, 20], null],
                $i % 10 < 6 ? ['residential_plot', 10, 'marla', null, 19_000_000, [5, 18], null] : null,
                $i % 10 < 3 ? ['residential_plot', 1, 'kanal', null, 40_000_000, [4, 16], null] : null,
                $i % 10 < 8 ? ($i % 2 ? ['house', 10, 'marla', null, 52_000_000, [4, 14], [3, 5]] : ['house', 5, 'marla', null, 24_000_000, [4, 15], [3.2, 5.5]]) : null,
            ])),
            'apartments' => [['apartment', 1100, 'sqft', 2, 17_000_000, [3, 13], [4, 7]], ['apartment', 1600, 'sqft', 3, 26_000_000, [3, 12], [3.8, 6.5]]],
            'mall' => [['shop', 200, 'sqft', null, 8_000_000, [3, 14], [6, 10]]],
            'plaza' => [['shop', 300, 'sqft', null, 8_500_000, [3, 12], [6, 9.5]], ['office', 800, 'sqft', null, 15_000_000, [2, 11], [5.5, 8.5]]],
            'tower' => [['office', 1000, 'sqft', null, 22_000_000, [2, 12], [5.5, 9]]],
            'land' => [['commercial_plot', 4, 'marla', null, 32_000_000, [5, 22], null], ['commercial_plot', 8, 'marla', null, 66_000_000, [5, 20], null]],
            'mixed' => [['apartment', 1200, 'sqft', 2, 20_000_000, [3, 12], [4, 6.5]], ['shop', 250, 'sqft', null, 9_000_000, [3, 13], [6, 9.5]]],
        };
        // How much history the project has, so data confidence varies like real data would:
        // most have five years, some three, a few eighteen months (provisional, no scenarios).
        // Upcoming projects have only recent asking prices: some stay provisional or unscored.
        $tier = $early ? 'early' : (($t = $rand(0, 1)) < 0.6 ? 'full' : ($t < 0.88 ? 'partial' : 'sparse'));
        $months = match ($tier) {
            'full' => [60, 54, 48, 42, 36, 30, 24, 18, 12, 6, 0],
            'partial' => [36, 30, 24, 18, 12, 6, 0],
            'sparse' => [18, 12, 6, 0],
            default => [12, 6, 0],
        };
        [$minSample, $maxSample] = match ($tier) {
            'full' => [6, 24], 'partial' => [3, 9], default => [2, 4]
        };

        foreach ($series as [$type, $size, $unit, $bedrooms, $base, $growth, $yield]) {
            $g = $rand($growth[0], $growth[1]) / 100;
            $current = $base * $level * $rand(0.75, 1.3);
            $basis = $early || $tier === 'sparse' || $rand(0, 1) < 0.25 ? 'asking' : 'transaction';
            foreach ($months as $m) {
                $amount = $current / pow(1 + $g, $m / 12) * $rand(0.985, 1.015);
                $this->observation($p, ['kind' => 'sale', 'price_basis' => $basis, 'asset_class' => $type === 'apartment' && $kind === 'mixed' ? 'residential' : ($type === 'shop' || $type === 'office' ? 'commercial' : $asset),
                    'property_type' => $type, 'size_value' => $size, 'size_unit' => $unit, 'bedrooms' => $bedrooms,
                    'average_amount' => (int) round($amount, -3), 'observed_on' => now()->subMonths($m + 1)->startOfMonth()->addDays(14)->toDateString(),
                    'sample_size' => (int) $rand($minSample, $maxSample), 'source_id' => $this->sources[$basis === 'asking' ? 'asking' : 'transactions']->id]);
            }
            if ($yield && $tier !== 'early') {
                $this->observation($p, ['kind' => 'rent', 'price_basis' => 'asking', 'asset_class' => $type === 'shop' || $type === 'office' ? 'commercial' : 'residential',
                    'property_type' => $type, 'size_value' => $size, 'size_unit' => $unit, 'bedrooms' => $bedrooms,
                    'average_amount' => (int) round($current * $rand($yield[0], $yield[1]) / 100 / 12, -2), 'observed_on' => now()->subMonths(2)->startOfMonth()->addDays(14)->toDateString(),
                    'sample_size' => (int) $rand(3, 15), 'source_id' => $this->sources['rents']->id]);
            }
        }

        $pick = fn (array $levels) => $levels[(int) floor($rand(0, count($levels) - 0.001))];
        $indicators = [
            'demand' => $pick(['very_high', 'high', 'high', 'medium', 'medium', 'low']),
            'supply' => $pick(['limited', 'balanced', 'balanced', 'high']),
            'liquidity' => $pick(['high', 'medium', 'medium', 'low']),
            'infrastructure' => $pick(['excellent', 'good', 'good', 'fair', 'poor']),
            'growth_drivers' => $pick(['strong', 'moderate', 'moderate', 'weak']),
            'legal_verification' => $early ? 'partially_verified' : $pick(['verified', 'verified', 'partially_verified', 'unverified', 'disputed']),
            'developer_status' => match ($p->status) {
                'completed' => 'completed', 'under_construction' => $pick(['on_track', 'on_track', 'delayed']), default => 'on_track'
            },
        ];
        foreach ($indicators as $indicator => $value) {
            $this->indicator($p, ['indicator' => $indicator, 'level' => $value]);
        }
        $this->indicator($p, ['indicator' => 'development_progress', 'numeric_value' => match ($p->status) {
            'completed' => 100, 'under_construction' => round($rand(30, 85)), default => round($rand(5, 20))
        }]);
        if (in_array($kind, ['mall', 'plaza', 'tower', 'mixed'], true) && $p->status === 'completed') {
            $this->indicator($p, ['indicator' => 'occupancy', 'numeric_value' => round($rand(68, 98), 1), 'asset_class' => 'commercial']);
        }
    }

    protected function observation(Project $p, array $a): void
    {
        $o = new MarketObservation($a + ['city' => $p->city, 'project_id' => $p->id, 'currency' => 'PKR', 'methodology' => self::METHODOLOGY,
            'notes' => 'Illustrative demo figure.']);
        $o->forceFill(['status' => 'demo', 'is_demo' => true])->save();
        $this->created['observations']++;
    }

    protected function indicator(Project $p, array $a): void
    {
        $i = new MarketIndicator($a + ['city' => $p->city, 'project_id' => $p->id, 'observed_on' => now()->subMonths(2)->toDateString(),
            'source_id' => $this->sources['transactions']->id, 'methodology' => self::METHODOLOGY, 'notes' => 'Illustrative demo indicator.']);
        $i->forceFill(['status' => 'demo', 'is_demo' => true])->save();
        $this->created['indicators']++;
    }

    protected function specifications(string $kind, callable $rand): array
    {
        $demo = ['Data' => 'Illustrative values'];

        return $demo + match ($kind) {
            'society' => ['Total area' => (int) $rand(200, 2500).' kanal (illustrative)', 'Plot sizes' => '5 Marla, 10 Marla, 1 Kanal', 'Amenities' => 'Park, mosque, school (illustrative)'],
            'apartments' => ['Floors' => (string) (int) $rand(8, 24), 'Units' => (string) (int) $rand(120, 480), 'Types' => '2 and 3 bedroom'],
            'mall' => ['Floors' => (string) (int) $rand(3, 8), 'Shops' => (string) (int) $rand(120, 420), 'Parking' => (int) $rand(300, 1500).' cars'],
            'plaza' => ['Floors' => (string) (int) $rand(4, 10), 'Shops / offices' => (int) $rand(40, 160).' / '.(int) $rand(20, 90)],
            'tower' => ['Floors' => (string) (int) $rand(12, 35), 'Office floor plate' => number_format((int) $rand(6, 18) * 1000).' sq ft', 'Parking' => (int) $rand(200, 900).' cars'],
            'land' => ['Plot sizes' => '4 Marla, 8 Marla', 'Road width' => (int) $rand(60, 150).' ft (illustrative)'],
            'mixed' => ['Components' => 'Apartments, retail, offices', 'Floors' => (string) (int) $rand(10, 28)],
            default => [],
        };
    }

    protected function galleryTypes(string $kind): array
    {
        return match ($kind) {
            'society' => ['houses', 'plots', 'society'],
            'apartments' => ['apartments', 'mixed'],
            'mall' => ['mall', 'shops'],
            'plaza' => ['plaza', 'shops', 'office'],
            'tower' => ['tower', 'office'],
            'land' => ['land', 'plaza'],
            'mixed' => ['mixed', 'apartments', 'shops'],
            default => [],
        };
    }
}
