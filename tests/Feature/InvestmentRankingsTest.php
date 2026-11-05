<?php

use App\Models\AiTask;
use App\Models\AppSetting;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
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

it('serves the dashboards beside the Investment Index, saying when verified data is insufficient', function () {
    rkMall('Fixture Mall Without Data');

    $this->get(route('rankings.index'))->assertOk()->assertSee('Meezan Investment Index')->assertSee('Insufficient Verified Data')->assertSee(config('meezan.investment.disclaimer'))
        ->assertSee(route('rankings.city', 'lahore'))->assertSee(route('rankings.city', 'pakistan'));
    foreach (['rankings.city' => ['pakistan'], 'rankings.city ' => ['lahore'], 'rankings.group' => ['lahore', 'residential'], 'rankings.group ' => ['pakistan', 'commercial'], 'rankings.segment' => ['lahore', 'commercial', 'shopping-malls'],
        'rankings.compare' => ['lahore', 'commercial', 'shopping-malls']] as $route => $params) {
        $this->get(route(trim($route), $params))->assertOk()->assertSee('Insufficient Verified Data');
    }

    $html = $this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->assertOk()
        ->assertSee('Shopping Malls')->assertSee('Fixture Mall Without Data')->assertSee('undergoing data verification')
        ->assertSee(route('rankings.category', ['shopping-malls', 'city' => 'Lahore']))
        ->assertSee('Forecast unavailable — insufficient verified data')->assertSee('noindex, follow', false)->getContent();
    expect($html)->not->toContain('MPIS Score</span>')->not->toMatch('/Rental Yield \(Annual, gross\)<\/dt><dd[^>]*>\d/');
    $this->get(route('rankings.city', 'pakistan'))->assertSee('noindex, follow', false)->assertSee('All Pakistan');
    $this->get(route('rankings.city', ['lahore', 'tab' => 'shopping-malls']))->assertOk()->assertSee('Fixture Mall Without Data');
});

it('keeps individual plots, houses, shops, offices and mixed use out of the public dashboards', function () {
    $mixed = rkMall('Fixture Mixed Use', ['category' => 'mixed_use']);
    foreach ([['residential', 'plots'], ['residential', 'houses'], ['commercial', 'commercial-shops'], ['commercial', 'office-spaces'], ['commercial', 'commercial-plots'], ['mixed-use', 'mixed-use']] as [$g, $seg]) {
        $this->get(route('rankings.segment', ['lahore', $g, $seg]))->assertStatus(301)->assertRedirect(route('rankings.city', 'lahore'));
        $this->get(route('rankings.compare', ['lahore', $g, $seg]))->assertStatus(301)->assertRedirect(route('rankings.city', 'lahore'));
    }
    $this->get(route('rankings.group', ['karachi', 'mixed-use']))->assertStatus(301)->assertRedirect(route('rankings.city', 'karachi'));
    $this->get(route('rankings.city', ['lahore', 'tab' => 'plots']))->assertStatus(301)->assertRedirect(route('rankings.city', 'lahore'));
    $this->get(route('rankings.city', ['lahore', 'tab' => 'nonsense']))->assertNotFound();

    expect(array_keys(RankingBrowser::publicSegments()))->toEqualCanonicalizing(['societies', 'apartments', 'shopping-malls', 'commercial-plazas', 'business-towers']);
    $page = $this->get(route('rankings.city', 'lahore'))->assertOk()->assertSee('Housing Societies')->assertSee('Apartment Buildings')->assertSee('Shopping Malls')->assertSee('Commercial Plazas')->assertSee('Business Towers');
    foreach (['tab=plots', 'tab=houses', 'tab=office-spaces', 'tab=mixed-use', 'residential/plots', 'commercial/commercial-shops'] as $link) {
        $page->assertDontSee($link);
    }

    // An apartment building is a development; mixed use and unit-level area rows are not listed under it.
    rkMall('Fixture Apartment Building', ['category' => 'apartment_complex']);
    $names = app(RankingBrowser::class)->publicItems('apartments', 'Lahore')->pluck('name')->all();
    expect($names)->toBe(['Fixture Apartment Building'])->not->toContain($mixed->name);
    $gulberg = area('Gulberg');
    rkDocument(['area_id' => $gulberg->id], 'residential', 'apartment', [10_000_000, 11_000_000, 12_000_000, 13_000_000], [1000, 'sqft'], 70_000, ['bedrooms' => 2]);
    rkPublish();
    expect(app(RankingBrowser::class)->items('apartments', 'Lahore')->where('project', null))->not->toBeEmpty()
        ->and(app(RankingBrowser::class)->publicItems('apartments', 'Lahore')->where('project', null))->toBeEmpty();
});

it('never shows a legally excluded project with a score, and shows Investment Index results by city', function () {
    $bad = rkMall('Fixture Excluded Mall');
    rkDocument(['project_id' => $bad->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    $good = rkMall('Fixture Clean Mall');
    rkDocument(['project_id' => $good->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    rkPublish();
    $run = new InvestmentIndexRun;
    $run->forceFill(['methodology_id' => InvestmentIndexMethodology::current()->id, 'methodology_version' => 1, 'trigger' => 'test', 'status' => 'published', 'published_at' => now(), 'started_at' => now(), 'finished_at' => now()])->save();
    $score = fn (Project $p, array $a) => (new InvestmentIndexScore)->forceFill($a + ['run_id' => $run->id, 'project_id' => $p->id, 'category' => 'shopping-malls', 'factors' => [],
        'risk_class' => 'moderate', 'legal_status' => 'approved', 'confidence_score' => 80, 'confidence_level' => 'high'])->save();
    $score($bad, ['status' => 'excluded', 'legal_status' => 'illegal', 'score' => null]);
    $score($good, ['status' => 'ranked', 'score' => 77.5, 'category_rank' => 1]);

    $items = app(RankingBrowser::class)->publicItems('shopping-malls', 'Lahore')->keyBy('name');
    expect($items['Fixture Excluded Mall']['status'])->toBe('excluded')->and($items['Fixture Excluded Mall']['score'])->toBeNull()
        ->and($items['Fixture Excluded Mall']['yield'])->toBeNull()->and($items['Fixture Excluded Mall']['row'])->toBeNull()
        ->and($items['Fixture Clean Mall']['score'])->not->toBeNull()->and($items->keys()->first())->toBe('Fixture Clean Mall');
    $this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->assertOk()->assertSee('Excluded: legal issue');
    // The historical snapshot itself is untouched.
    expect(app(RankingBrowser::class)->items('shopping-malls', 'Lahore')->firstWhere('name', 'Fixture Excluded Mall')['score'])->not->toBeNull();

    $this->get(route('rankings.city', 'lahore'))->assertOk()->assertSee('Meezan Investment Index in Lahore')->assertSee('Fixture Clean Mall')->assertSee('77.5');
    $this->get(route('rankings.city', 'karachi'))->assertOk()->assertDontSee('Fixture Clean Mall')->assertSee('Insufficient Verified Data');
});

/** A real published snapshot, and a demo snapshot with one fictional mall. @return array{0: InvestmentCalculation, 1: Project, 2: InvestmentCalculation} */
function rkDemoSnapshot(): array
{
    $real = rkMall('Fixture Real Mall');
    rkDocument(['project_id' => $real->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    $realCalc = rkPublish();
    $demo = rkMall('Fixture Fictional Mall');
    $demo->forceFill(['is_demo' => true])->save();
    // The fixture copies the real row's analysis fields; only its identity differs. Published snapshots are immutable, so it is built first.
    $analysis = InvestmentRanking::where('calculation_id', $realCalc->id)->where('segment', 'shopping-malls')->first();
    $calc = new InvestmentCalculation;
    $calc->forceFill(['methodology_id' => $realCalc->methodology_id, 'methodology_version' => $realCalc->methodology_version, 'scope' => 'all', 'status' => 'completed', 'includes_demo' => true, 'trigger' => 'demo'])->save();
    $row = new InvestmentRanking;
    $row->forceFill(['calculation_id' => $calc->id, 'methodology_version' => 1, 'category' => 'segment', 'segment' => 'shopping-malls', 'entity_type' => 'project', 'entity_id' => $demo->id, 'entity_name' => $demo->name,
        'country' => 'Pakistan', 'city' => 'Lahore', 'asset_class' => 'commercial', 'status' => 'ranked', 'is_demo' => true, 'rank' => 1, 'score' => 99, 'confidence' => 'high', 'confidence_score' => 90, 'risk' => 'low', 'factors' => $analysis->factors, 'metrics' => $analysis->metrics])->save();
    $calc->forceFill(['status' => 'published', 'published_at' => now()->addDay()])->save();

    return [$realCalc, $demo, $calc];
}

it('keeps demo rankings out of every public page, even with demo mode on', function () {
    [$realCalc, $demo] = rkDemoSnapshot();
    AppSetting::put('investment.demo_mode', true);

    expect(InvestmentCalculation::demoMode())->toBeTrue()->and(InvestmentCalculation::demoVisible())->toBeFalse()
        ->and(InvestmentCalculation::current()->id)->toBe($realCalc->id);
    foreach ([route('rankings.index'), route('rankings.city', 'pakistan'), route('rankings.city', 'lahore'), route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']), route('investment.index')] as $url) {
        $this->get($url)->assertOk()->assertDontSee('DEMO DATA')->assertDontSee('Fixture Fictional Mall');
    }
    $this->get(route('projects.show', $demo))->assertNotFound();

    // A signed-in buyer is still the public.
    $this->actingAs(User::factory()->create());
    expect(InvestmentCalculation::demoVisible())->toBeFalse()->and(InvestmentCalculation::current()->id)->toBe($realCalc->id);
    $this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->assertDontSee('Fixture Fictional Mall');
});

it('previews demo rankings, labelled and not indexed, only for an administrator with demo mode on', function () {
    [, , $calc] = rkDemoSnapshot();
    AppSetting::put('investment.demo_mode', true);
    $this->actingAs(User::factory()->admin()->create());

    expect(InvestmentCalculation::current()->id)->toBe($calc->id);
    $this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->assertOk()->assertSee('DEMO DATA')->assertSee('Fixture Fictional Mall')->assertSee('noindex, follow', false);
    $this->get(route('admin.market.calculations.show', $calc))->assertOk();
});

it('keeps demo calculations as history when demo mode is off, also for administrators', function () {
    [$realCalc, , $calc] = rkDemoSnapshot();
    $this->actingAs(User::factory()->admin()->create());

    expect(InvestmentCalculation::current()->id)->toBe($realCalc->id);
    $this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->assertOk()->assertDontSee('Fixture Fictional Mall');
    expect(InvestmentCalculation::find($calc->id)->status)->toBe('published')->and(InvestmentRanking::where('calculation_id', $calc->id)->count())->toBe(1);
    $this->get(route('admin.market.calculations.show', $calc))->assertOk();
});

it('sends no model request and starts no task for advisor questions when nothing verified is published', function () {
    Http::preventStrayRequests();
    config(['services.gemini.key' => 'test-key']);
    $tasks = AiTask::count();

    $this->getJson(route('rankings.advisor', ['question' => 'Which shopping mall is best in Lahore?']))->assertOk()
        ->assertJsonPath('mode', 'rules-no-data')->assertJsonPath('task', null)->assertJsonPath('answer.0', fn ($a) => str_contains($a, 'No investment rankings are published yet'))
        ->assertJsonPath('missing', fn ($m) => str_contains(implode(' ', $m), 'Insufficient Verified Data'));
    $this->get(route('rankings.city', ['lahore', 'ask' => 'Which mall is best?']))->assertOk()->assertSee('No investment rankings are published yet');
    expect(AiTask::count())->toBe($tasks);
    Http::assertNothingSent();
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
    $this->get('/investment-rankings/casinos')->assertNotFound();
    $this->get('/investment-rankings/lahore/commercial/casinos')->assertNotFound();
    $this->get('/investment-rankings/pakistan')->assertOk()->assertSee('Choose an investment category');
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

it('filters, searches and sorts the earlier segment snapshot used by the AI tools', function () {
    $gulberg = area('Gulberg');
    rkDocument(['area_id' => $gulberg->id], 'residential', 'residential_plot', [9_000_000, 10_000_000, 11_000_000, 12_000_000], [5, 'marla']);
    rkDocument(['area_id' => $gulberg->id], 'residential', 'residential_plot', [18_000_000, 19_000_000, 21_000_000, 23_000_000], [10, 'marla']);
    rkPublish();
    $browser = app(RankingBrowser::class);

    expect($browser->items('plots', 'Lahore', ['size' => '5-marla'])->pluck('size_key')->all())->toBe(['5-marla'])
        ->and($browser->items('plots', 'Lahore', ['q' => 'nowhere']))->toBeEmpty()
        ->and($browser->items('plots', 'Lahore', ['sort' => 'price', 'dir' => 'desc'])->first()['size_key'])->toBe('10-marla')
        ->and($browser->items('plots', 'Karachi'))->toBeEmpty();

    // The public plot ranking page is retired: plots are not ranked by the Investment Index.
    $this->get(route('rankings.segment', ['lahore', 'residential', 'plots', 'size' => '10-marla']))->assertRedirect(route('rankings.city', 'lahore'));
});

it('filters, sorts and updates the public category table in place', function () {
    foreach (['Fixture Filter Alpha' => 11_000_000, 'Fixture Filter Beta' => 15_000_000] as $name => $price) {
        $mall = rkMall($name);
        rkDocument(['project_id' => $mall->id], 'commercial', 'shop', [$price - 3_000_000, $price - 2_000_000, $price - 1_000_000, $price], [200, 'sqft'], 60_000);
    }
    rkPublish();
    $browser = app(RankingBrowser::class);

    expect($browser->publicItems('shopping-malls', 'Lahore', ['q' => 'Beta'])->pluck('name')->all())->toBe(['Fixture Filter Beta'])
        ->and($browser->publicItems('shopping-malls', 'Lahore', ['sort' => 'price', 'dir' => 'desc'])->first()['name'])->toBe('Fixture Filter Beta')
        ->and($browser->publicItems('shopping-malls', 'Karachi'))->toBeEmpty();
    $this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls', 'q' => 'Alpha']))->assertOk()->assertSee('Fixture Filter Alpha')->assertDontSee('noindex, follow', false);
    // The in-place update returns only the results block.
    $partial = $this->withHeader('X-Rankings-Partial', '1')->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls', 'sort' => 'price']))->assertOk()->getContent();
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

it('maps entries with a known position, and leaves out those without one', function () {
    $mall = rkMall('Fixture Mapped Mall');
    rkDocument(['project_id' => $mall->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    rkMall('Fixture Unmapped Mall', ['latitude' => null, 'longitude' => null]);
    rkPublish();

    $markers = markersOn($this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->getContent());
    expect(collect($markers)->pluck('name')->all())->toBe(['Fixture Mapped Mall'])
        ->and($markers[0]['approximate'])->toBeFalse()->and($markers[0]['score'])->not->toBeNull();
});

it('indexes only dashboards with verified scored entries and lists only those in the sitemap', function () {
    $mall = rkMall('Fixture Indexed Mall');
    rkDocument(['project_id' => $mall->id], 'commercial', 'shop', [8_000_000, 9_000_000, 10_000_000, 11_000_000], [200, 'sqft'], 60_000);
    rkPublish();

    $html = $this->get(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->assertOk()->getContent();
    expect($html)->toContain('<link rel="canonical" href="'.route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']).'">')
        ->toContain('"@type":"ItemList"')->toContain('"@type":"BreadcrumbList"')->not->toContain('noindex');
    $this->get(route('rankings.segment', ['lahore', 'commercial', 'business-towers']))->assertSee('noindex, follow', false);

    $sitemap = $this->get(route('sitemap.show', 'pages'))->assertOk()->getContent();
    expect($sitemap)->toContain(route('rankings.segment', ['lahore', 'commercial', 'shopping-malls']))->toContain(route('rankings.city', 'lahore'))
        ->toContain(route('rankings.index'))->not->toContain(route('rankings.segment', ['lahore', 'commercial', 'business-towers']))
        ->not->toContain('commercial-shops');
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
    config(['meezan.investment.ai_driver' => 'llm', 'services.gemini.key' => 'test-key']);
    Http::fake(['*' => Http::sequence()
        ->push(['candidates' => [['content' => ['parts' => [['text' => 'Fixture Worded Mall will return 40% next year.']]], 'finishReason' => 'STOP']]])
        ->push(['candidates' => [['content' => ['parts' => [['text' => 'Fixture Worded Mall is the top-ranked mall in the verified data; scores are not guarantees.']]], 'finishReason' => 'STOP']]])]);

    expect(app(InvestmentAdvisor::class)->answer('Best mall in Lahore?')['mode'])->toBe('rules');
    $worded = app(InvestmentAdvisor::class)->answer('Best mall in Lahore?');
    expect($worded['mode'])->toBe('llm')->and($worded['answer'][0])->toContain('top-ranked');
});

/*
| Hero search, map, SEO
*/

it('sends investment searches to the closest Investment Index leaderboard', function () {
    $this->get('/search?mode=investment&tab=rent&investment_city=karachi')->assertRedirect(route('rankings.category', ['apartment-buildings', 'city' => 'Karachi']));
    $this->get('/search?mode=investment&tab=sale&investment_city=multan')->assertRedirect(route('rankings.category', ['housing-societies', 'city' => 'Multan']));
    $this->get('/search?mode=investment&tab=commercial&investment_city=pakistan')->assertRedirect(route('rankings.index'));
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
    $this->get(route('rankings.project', $p))->assertOk()->assertSee('Fixture Business Tower')->assertSee('Business tower');

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
