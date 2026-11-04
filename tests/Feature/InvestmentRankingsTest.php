<?php

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentRanking;
use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use App\Services\Investment\InvestmentAdvisor;
use App\Services\Investment\RankingBrowser;
use App\Services\Investment\RankingCalculator;
use App\Support\PriceChart;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/*
| Investment Rankings by city and property type. Every market figure here
| is a test fixture in the test database only.
*/

function rkSource(): MarketSource
{
    return MarketSource::firstOrCreate(['name' => 'Rankings Test Registry'], ['type' => 'verified_transactions', 'reliability' => 'high', 'is_active' => true]);
}

function rkObservation(array $a, string $status = 'verified'): MarketObservation
{
    $o = new MarketObservation($a + ['kind' => 'sale', 'price_basis' => 'transaction', 'city' => 'Lahore', 'currency' => 'PKR', 'sample_size' => 10,
        'source_id' => rkSource()->id, 'methodology' => 'Fixture observation for tests.']);
    $o->forceFill(['status' => $status])->save();

    return $o;
}

function rkIndicator(array $a, string $status = 'verified'): MarketIndicator
{
    $i = new MarketIndicator($a + ['city' => 'Lahore', 'observed_on' => now()->subMonth(), 'source_id' => rkSource()->id, 'methodology' => 'Fixture indicator for tests.']);
    $i->forceFill(['status' => $status])->save();

    return $i;
}

/** Four yearly sale observations (oldest first) and a rent, plus the indicators a full score needs. */
function rkDocument(array $where, string $asset, string $type, array $prices, ?array $size = null, ?int $rent = null, array $extra = []): void
{
    foreach (array_values($prices) as $n => $price) {
        rkObservation($where + ['asset_class' => $asset, 'property_type' => $type, 'average_amount' => $price, 'observed_on' => now()->subMonths((count($prices) - 1 - $n) * 12 + 1)]
            + ($size ? ['size_value' => $size[0], 'size_unit' => $size[1]] : []) + $extra);
    }
    if ($rent) {
        rkObservation($where + ['kind' => 'rent', 'price_basis' => 'asking', 'asset_class' => $asset, 'property_type' => $type, 'average_amount' => $rent, 'observed_on' => now()->subMonths(2)]
            + ($size ? ['size_value' => $size[0], 'size_unit' => $size[1]] : []) + $extra);
    }
    foreach (['demand' => 'high', 'supply' => 'balanced', 'liquidity' => 'high', 'infrastructure' => 'good', 'growth_drivers' => 'moderate', 'legal_verification' => 'verified'] as $k => $level) {
        rkIndicator($where + ['indicator' => $k, 'level' => $level]);
    }
    rkIndicator($where + ['indicator' => 'development_progress', 'numeric_value' => 90]);
}

function rkMall(string $name, array $attributes = []): Project
{
    $p = new Project($attributes + ['name' => $name, 'category' => 'shopping_mall', 'city' => 'Lahore', 'status' => 'completed',
        'description' => 'A fixture shopping mall used only by the automated tests.', 'latitude' => 31.51, 'longitude' => 74.34]);
    $p->is_published = true;
    $p->save();

    return $p;
}

function rkPublish(): InvestmentCalculation
{
    $admin = User::factory()->admin()->create();
    $calc = app(RankingCalculator::class)->run($admin);
    expect($calc->status)->toBe('completed');
    app(RankingCalculator::class)->publish($calc, $admin);

    return $calc->fresh();
}

/*
| Without data: honest empty states
*/

it('shows every page with live counts and no invented figures when nothing is published', function () {
    rkMall('Fixture Mall Without Data');

    foreach (['rankings.index' => [], 'rankings.city' => ['lahore'], 'rankings.group' => ['lahore', 'residential'], 'rankings.segment' => ['lahore', 'commercial', 'shopping-malls'],
        'rankings.compare' => ['lahore', 'commercial', 'shopping-malls']] as $route => $params) {
        $this->get(route($route, $params))->assertOk();
    }
    $this->get(route('rankings.index'))->assertSee('No rankings are published yet')->assertSee('Smart Real Estate')->assertSee(config('meezan.investment.disclaimer'));

    $html = $this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->assertOk()
        ->assertSee('Top')->assertSee('Shopping Malls')->assertSee('Fixture Mall Without Data')->assertSee('Insufficient Data')
        ->assertSee('Forecast unavailable — insufficient verified data')->assertSee('noindex, follow', false)->getContent();
    expect($html)->not->toContain('MPIS Score</span>')->not->toMatch('/Rental Yield \(Annual, gross\)<\/dt><dd[^>]*>\d/');
});

it('counts real records for city cards and the overview', function () {
    rkMall('Fixture Mall One');
    rkMall('Hidden Fixture Mall')->forceFill(['is_published' => false])->save();
    $cities = collect(app(RankingBrowser::class)->cities())->keyBy('name');
    $overview = app(RankingBrowser::class)->overview('Lahore');

    expect($cities['Lahore']['commercial_projects'])->toBe(1)
        ->and($cities['Karachi']['commercial_projects'])->toBe(0)
        ->and($cities['Lahore']['societies'])->toBe(Area::active()->where('city', 'Lahore')->whereIn('type', RankingBrowser::segment('societies')['area_types'])->count())
        ->and($overview['shopping_malls'])->toBe(1)
        ->and($overview['ranked'])->toBe(0);
});

it('rejects unknown cities, groups and categories', function () {
    $this->get('/investment-rankings/london')->assertNotFound();
    $this->get('/investment-rankings/lahore/industrial')->assertNotFound();
    $this->get('/investment-rankings/lahore/residential/shopping-malls')->assertNotFound();
    $this->get('/investment-rankings/lahore?tab=nonsense')->assertNotFound();
    $this->get('/investment-rankings/pakistan')->assertRedirect(route('rankings.index'));
});

/*
| Property-type rankings from the existing methodology
*/

it('ranks each property type separately with the existing scorer and methodology', function () {
    $mall = rkMall('Fixture Mall Alpha');
    rkDocument(['project_id' => $mall->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    rkIndicator(['project_id' => $mall->id, 'indicator' => 'occupancy', 'numeric_value' => 91]);
    $gulberg = area('Gulberg');
    rkDocument(['area_id' => $gulberg->id], 'residential', 'residential_plot', [9_000_000, 10_000_000, 11_000_000, 12_000_000], [5, 'marla']);
    rkDocument(['area_id' => $gulberg->id], 'residential', 'residential_plot', [18_000_000, 19_000_000, 21_000_000, 23_000_000], [10, 'marla']);
    $calc = rkPublish();

    $rows = InvestmentRanking::where('calculation_id', $calc->id)->whereNotNull('segment')->get();
    $mallRow = $rows->where('segment', 'shopping-malls')->sole();
    expect($mallRow->category)->toBe('segment')->and($mallRow->status)->toBe('ranked')
        ->and($mallRow->methodology_version)->toBe($calc->methodology_version)
        ->and($mallRow->factors['weights_from'])->toBe('rental')
        ->and($mallRow->metric('occupancy'))->toEqual(91)
        ->and($mallRow->metric('current.per_sqft'))->toBe(55_000)
        ->and($mallRow->metric('coordinates'))->toEqual([31.51, 74.34]);

    // Plots: one row per size, never mixing 5 and 10 Marla prices.
    $plots = $rows->where('segment', 'plots')->where('entity_id', $gulberg->id);
    expect($plots->pluck('size_key')->sort()->values()->all())->toBe(['10-marla', '5-marla'])
        ->and($plots->firstWhere('size_key', '5-marla')->metric('current.amount'))->toBe(12_000_000)
        ->and($plots->firstWhere('size_key', '10-marla')->metric('current.amount'))->toBe(23_000_000);

    // Plot observations never reach the shop or apartment views; the mall never reaches societies.
    expect($rows->where('segment', 'commercial-shops')->pluck('entity_type')->unique()->all())->toBe(['project'])
        ->and($rows->where('segment', 'apartments'))->toBeEmpty()
        ->and($rows->where('segment', 'societies')->where('entity_type', 'project'))->toBeEmpty();

    // The general rankings are unchanged: segment rows stay out of them.
    expect(InvestmentRanking::where('calculation_id', $calc->id)->where('category', 'overall')->whereNotNull('segment')->count())->toBe(0);
    $this->get(route('investment.show', ['area', $gulberg->id]))->assertOk()->assertDontSee('Segment');
});

it('groups apartments by bedrooms', function () {
    $tower = rkMall('Fixture Apartments', ['category' => 'apartment_complex']);
    rkDocument(['project_id' => $tower->id], 'residential', 'apartment', [10_000_000, 11_000_000, 12_000_000, 13_000_000], [1000, 'sqft'], 70_000, ['bedrooms' => 2]);
    rkDocument(['project_id' => $tower->id], 'residential', 'apartment', [14_000_000, 15_000_000, 16_000_000, 17_000_000], [1000, 'sqft'], 90_000, ['bedrooms' => 3]);
    $calc = rkPublish();

    $rows = InvestmentRanking::where('calculation_id', $calc->id)->where('segment', 'apartments')->get();
    expect($rows->pluck('size_key')->sort()->values()->all())->toBe(['2-bed', '3-bed'])
        ->and($rows->firstWhere('size_key', '3-bed')->metric('group_label'))->toBe('3 Bedroom');
});

it('labels entries ranked, provisional, under review or insufficient without scoring missing data', function () {
    $documented = rkMall('Fixture Documented Mall');
    rkDocument(['project_id' => $documented->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    $pending = rkMall('Fixture Pending Mall');
    rkObservation(['project_id' => $pending->id, 'asset_class' => 'commercial', 'property_type' => 'shop', 'average_amount' => 9_000_000, 'observed_on' => now()->subMonth()], 'submitted');
    rkMall('Fixture Empty Mall');
    rkPublish();

    $items = app(RankingBrowser::class)->items('shopping-malls', 'Lahore')->keyBy('name');
    expect($items['Fixture Documented Mall']['status'])->toBeIn(['ranked', 'provisional'])
        ->and($items['Fixture Documented Mall']['score'])->not->toBeNull()
        ->and($items['Fixture Pending Mall']['status'])->toBe('review')
        ->and($items['Fixture Pending Mall']['score'])->toBeNull()
        ->and($items['Fixture Empty Mall']['status'])->toBe('insufficient')
        ->and($items['Fixture Empty Mall']['score'])->toBeNull()
        ->and($items->keys()->first())->toBe('Fixture Documented Mall');
});

it('filters, searches and sorts the ranking table', function () {
    $gulberg = area('Gulberg');
    rkDocument(['area_id' => $gulberg->id], 'residential', 'residential_plot', [9_000_000, 10_000_000, 11_000_000, 12_000_000], [5, 'marla']);
    rkDocument(['area_id' => $gulberg->id], 'residential', 'residential_plot', [18_000_000, 19_000_000, 21_000_000, 23_000_000], [10, 'marla']);
    rkPublish();
    $browser = app(RankingBrowser::class);

    expect($browser->items('plots', 'Lahore', ['size' => '5-marla'])->pluck('size_key')->all())->toBe(['5-marla'])
        ->and($browser->items('plots', 'Lahore', ['q' => 'nowhere']))->toBeEmpty()
        ->and($browser->items('plots', 'Lahore', ['sort' => 'price', 'dir' => 'desc'])->first()['size_key'])->toBe('10-marla')
        ->and($browser->items('plots', 'Karachi'))->toBeEmpty();

    $this->get(route('rankings.segment', ['lahore', 'residential', 'plots', 'size' => '10-marla']))->assertOk()->assertSee('10 Marla')->assertDontSee('noindex, follow', false);
    // The in-place update returns only the results block.
    $partial = $this->withHeader('X-Rankings-Partial', '1')->get(route('rankings.segment', ['lahore', 'residential', 'plots', 'sort' => 'price']))->assertOk()->getContent();
    expect($partial)->toStartWith('<div data-rankings-results>')->not->toContain('<html');
});

/*
| Performance, charts, forecasts, comparison
*/

it('draws the price chart only from verified observations and keeps forecasts as scenarios', function () {
    expect(PriceChart::build([['date' => '2025-01-01', 'amount' => 100]]))->toBeNull();
    $chart = PriceChart::build([['date' => '2023-01-01', 'amount' => 100], ['date' => '2024-01-01', 'amount' => 120], ['date' => '2025-01-01', 'amount' => 150]], '1y');
    expect($chart['count'])->toBe(2);
    expect(PriceChart::build([['date' => '2023-01-01', 'amount' => 100], ['date' => '2025-01-01', 'amount' => 150]], '1y'))->toBeNull();

    $mall = rkMall('Fixture Chart Mall');
    rkDocument(['project_id' => $mall->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    rkPublish();
    $html = $this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls', 'selected' => 'project:'.$mall->id, 'period' => 'all']))->assertOk()->getContent();

    expect($html)->toContain('4 verified observations of one comparable series')
        ->toContain('Conservative')->toContain('These are illustrations of past rates continuing, not predictions or guarantees.')
        ->toContain('Asking prices, transactions and valuations are never mixed');
});

it('compares only entries of the same segment and highlights a winner only when all have the metric', function () {
    $a = rkMall('Fixture Mall A');
    rkDocument(['project_id' => $a->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    $b = rkMall('Fixture Mall B');
    rkDocument(['project_id' => $b->id], 'commercial', 'shop', [8_000_000, 8_500_000, 9_000_000, 9_500_000], [200, 'sqft']);
    rkPublish();

    $this->get(route('rankings.compare', ['lahore', 'commercial', 'shopping-malls', 'items' => ['project:'.$a->id, 'project:'.$b->id]]))->assertOk()
        ->assertSee('Fixture Mall A')->assertSee('Fixture Mall B')->assertSee('No verified data');
    $this->get(route('rankings.compare', ['lahore', 'commercial', 'shopping-malls', 'items' => ['bad key']]))->assertSessionHasErrors('items.0');
});

/*
| AI Investment Advisor
*/

it('answers from the published snapshot and says when data is missing', function () {
    $advisor = app(InvestmentAdvisor::class);
    expect($advisor->answer('Which shopping mall is best for investment in Lahore?')['answer'][0])->toContain('No investment rankings are published yet');

    $mall = rkMall('Fixture Advisor Mall');
    rkDocument(['project_id' => $mall->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    rkPublish();
    $advisor = app(InvestmentAdvisor::class); // a fresh instance reads the new publication

    $best = $advisor->answer('Which shopping mall is best for investment in Lahore?');
    expect($best['segment'])->toBe('shopping-malls')->and($best['city'])->toBe('Lahore')->and(implode(' ', $best['answer']))->toContain('Fixture Advisor Mall')
        ->and($best['sources'])->toContain('Rankings Test Registry');
    expect($advisor->answer('Which mall has the best rental yield in Lahore?')['intent'])->toBe('rental')
        ->and($advisor->answer('Compare 5 Marla plots in Lahore')['size'])->toBe('5-marla')
        ->and($advisor->answer('What are the risks of this mall in Lahore?')['intent'])->toBe('risk');
    expect(implode(' ', $advisor->answer('Best business tower in Karachi?')['answer']))->toContain('There is not enough verified market data');

    $this->getJson(route('rankings.advisor', ['question' => 'Which shopping mall is best in Lahore?']))->assertOk()
        ->assertJsonPath('items.0.name', 'Fixture Advisor Mall')->assertJsonPath('mode', 'rules-agent'); // answered by the Investment Analyst agent
    $this->getJson(route('rankings.advisor', ['question' => 'x']))->assertUnprocessable();
});

it('keeps model wording only when it adds no figures', function () {
    $mall = rkMall('Fixture Worded Mall');
    rkDocument(['project_id' => $mall->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    rkPublish();
    config(['meezan.investment.ai_driver' => 'llm', 'services.openai.key' => 'test-key']);
    Http::fake(['*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => 'Fixture Worded Mall will return 40% next year.']]]])
        ->push(['choices' => [['message' => ['content' => 'Fixture Worded Mall is the top-ranked mall in the verified data; scores are not guarantees.']]]])]);

    expect(app(InvestmentAdvisor::class)->answer('Best mall in Lahore?')['mode'])->toBe('rules');
    $worded = app(InvestmentAdvisor::class)->answer('Best mall in Lahore?');
    expect($worded['mode'])->toBe('llm')->and($worded['answer'][0])->toContain('top-ranked');
});

/*
| Hero search, map, SEO
*/

it('sends the hero search to the chosen city and category', function () {
    $this->get(route('rankings.index', ['go' => 1, 'city' => 'karachi', 'segment' => 'apartments', 'min_yield' => 5]))
        ->assertRedirect(route('rankings.segment', ['karachi', 'residential', 'apartments', 'min_yield' => 5]));
    $this->get(route('rankings.index', ['go' => 1, 'city' => 'multan']))->assertRedirect(route('rankings.city', ['multan']));
    $this->get(route('rankings.index', ['go' => 1, 'city' => 'pakistan']))->assertRedirect(route('rankings.index'));
});

it('maps entries with a known position, and leaves out those without one', function () {
    $mall = rkMall('Fixture Mapped Mall');
    rkDocument(['project_id' => $mall->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    rkMall('Fixture Unmapped Mall', ['latitude' => null, 'longitude' => null]);
    rkPublish();

    $markers = markersOn($this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->getContent());
    expect(collect($markers)->pluck('name')->all())->toBe(['Fixture Mapped Mall'])
        ->and($markers[0]['approximate'])->toBeFalse()->and($markers[0]['score'])->not->toBeNull();
});

it('indexes only pages with scored entries and lists them in the sitemap', function () {
    $mall = rkMall('Fixture Indexed Mall');
    rkDocument(['project_id' => $mall->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    rkPublish();

    $html = $this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->assertOk()->getContent();
    expect($html)->toContain('<link rel="canonical" href="'.route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']).'">')
        ->toContain('"@type":"ItemList"')->toContain('"@type":"BreadcrumbList"')->not->toContain('noindex');
    $this->get(route('rankings.segment', ['lahore', 'commercial', 'business-towers']))->assertSee('noindex, follow', false);

    $sitemap = $this->get(route('sitemap.show', 'pages'))->assertOk()->getContent();
    expect($sitemap)->toContain(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))
        ->toContain(route('rankings.index'))->not->toContain(route('rankings.segment', ['lahore', 'commercial', 'business-towers']));
});

/*
| Admin data centre
*/

it('lets admins categorise and locate projects, with specifications and validation', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.projects.store'), [
        'name' => 'Fixture Business Tower', 'city' => 'Lahore', 'status' => 'under_construction', 'category' => 'business_tower',
        'latitude' => 31.52, 'longitude' => 74.35, 'launched_on' => now()->subMonths(2)->toDateString(), 'approval_status' => 'noc_issued',
        'description' => 'A fixture business tower used only by the automated tests.', 'specifications' => "Floors: 20\nParking: 400 cars\nnot a pair", 'is_published' => 1,
    ])->assertRedirect(route('admin.projects.index'));

    $p = Project::where('name', 'Fixture Business Tower')->sole();
    expect($p->category)->toBe('business_tower')->and($p->specifications)->toBe(['Floors' => '20', 'Parking' => '400 cars'])
        ->and($p->latitude)->toEqual(31.52)->and($p->launched_on->isPast())->toBeTrue();
    $this->get(route('rankings.segment', ['lahore', 'commercial', 'business-towers']))->assertSee('Fixture Business Tower');

    $this->post(route('admin.projects.store'), ['name' => 'Bad', 'city' => 'Lahore', 'status' => 'completed', 'category' => 'casino', 'latitude' => 51.5, 'longitude' => 74,
        'description' => str_repeat('x', 40)])->assertSessionHasErrors(['category', 'latitude']);

    $this->get(route('admin.market.categories'))->assertOk()->assertSee('Business Towers')->assertSee('1 published');
    $this->actingAs(User::factory()->create())->get(route('admin.market.categories'))->assertForbidden();
});

it('imports projects from CSV without overwriting and reports rejected rows', function () {
    $admin = User::factory()->admin()->create();
    rkMall('Existing Fixture Mall');
    $csv = "name,category,city,status,description,latitude,longitude\n"
        ."New Fixture Plaza,commercial_plaza,Lahore,completed,A fixture plaza imported by the automated tests only.,31.5,74.3\n"
        ."Existing Fixture Mall,shopping_mall,Lahore,completed,This row must not change the existing project at all.,31.6,74.4\n"
        ."Broken Row,spaceport,Lahore,completed,short,99,74\n";
    $file = UploadedFile::fake()->createWithContent('projects.csv', $csv);

    $this->actingAs($admin)->post(route('admin.projects.import.store'), ['file' => $file])->assertRedirect(route('admin.projects.import'))
        ->assertSessionHas('status', '1 imported (hidden until published), 1 already existed, 1 rejected.');
    $import = session('import');
    expect($import['errors'][0]['row'])->toBe(4)->and(implode(' ', $import['errors'][0]['errors']))->toContain('category');

    $new = Project::where('name', 'New Fixture Plaza')->sole();
    expect($new->is_published)->toBeFalse()->and($new->category)->toBe('commercial_plaza')
        ->and(Project::where('name', 'Existing Fixture Mall')->sole()->latitude)->toEqual(31.51)
        ->and(AuditLog::where('action', 'project.imported')->exists())->toBeTrue();
    $this->get(route('admin.projects.template'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('records apartment bedrooms on observations without changing older fingerprints', function () {
    $base = ['kind' => 'sale', 'price_basis' => 'transaction', 'city' => 'Lahore', 'property_type' => 'apartment', 'size_value' => 1000, 'size_unit' => 'sqft',
        'currency' => 'PKR', 'observed_on' => '2025-01-01', 'average_amount' => 10_000_000, 'source_id' => 1];
    expect(MarketObservation::fingerprintFor($base))->toBe(MarketObservation::fingerprintFor($base + ['bedrooms' => null]))
        ->and(MarketObservation::fingerprintFor($base + ['bedrooms' => 2]))->not->toBe(MarketObservation::fingerprintFor($base + ['bedrooms' => 3]))
        ->and(strlen(MarketObservation::fingerprintFor($base + ['bedrooms' => 2])))->toBe(64);
});
