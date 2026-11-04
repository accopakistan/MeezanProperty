<?php

use App\Models\AiAgent;
use App\Models\AiTask;
use App\Models\AppSetting;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentRanking;
use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use App\Services\Investment\AdvisorGateway;
use App\Services\Investment\DemoDataManager;
use App\Services\Investment\RankingBrowser;
use App\Services\Investment\RankingCalculator;

/*
| Investment Rankings demonstration dataset: flagged, separately ranked,
| clearly disclosed, removable without touching real data. Demo mode is
| off by default (real verified data only); these tests switch the
| preview on, as an admin would, to check how it is disclosed.
*/

beforeEach(fn () => AppSetting::put('investment.demo_mode', true));

function demoSeed(float $scale = 0.2, array $cities = ['Lahore']): InvestmentCalculation
{
    app(DemoDataManager::class)->seed($cities, $scale);

    return app(DemoDataManager::class)->recalculate(User::factory()->admin()->create());
}

/** One real, verified, ranked project (fixture), so demo and real data can be told apart. */
function realRankedMall(): Project
{
    $src = MarketSource::create(['name' => 'Real Test Registry', 'type' => 'verified_transactions', 'reliability' => 'high', 'is_active' => true]);
    $p = new Project(['name' => 'Real Fixture Mall', 'category' => 'shopping_mall', 'city' => 'Lahore', 'status' => 'completed', 'description' => 'A real-data fixture mall used only by the automated tests.']);
    $p->is_published = true;
    $p->save();
    foreach ([37, 25, 13, 1] as $n => $months) {
        $o = new MarketObservation(['kind' => 'sale', 'price_basis' => 'transaction', 'city' => 'Lahore', 'project_id' => $p->id, 'asset_class' => 'commercial', 'property_type' => 'shop',
            'size_value' => 200, 'size_unit' => 'sqft', 'currency' => 'PKR', 'average_amount' => 8_000_000 + $n * 900_000, 'observed_on' => now()->subMonths($months), 'sample_size' => 9,
            'source_id' => $src->id, 'methodology' => 'Fixture observation for tests.']);
        $o->forceFill(['status' => 'verified'])->save();
    }
    $rent = new MarketObservation(['kind' => 'rent', 'price_basis' => 'asking', 'city' => 'Lahore', 'project_id' => $p->id, 'asset_class' => 'commercial', 'property_type' => 'shop',
        'size_value' => 200, 'size_unit' => 'sqft', 'currency' => 'PKR', 'average_amount' => 65_000, 'observed_on' => now()->subMonths(2), 'sample_size' => 6, 'source_id' => $src->id, 'methodology' => 'Fixture rent for tests.']);
    $rent->forceFill(['status' => 'verified'])->save();
    foreach (['demand' => 'high', 'supply' => 'balanced', 'liquidity' => 'high', 'infrastructure' => 'good', 'growth_drivers' => 'moderate', 'legal_verification' => 'verified'] as $k => $level) {
        $i = new MarketIndicator(['city' => 'Lahore', 'project_id' => $p->id, 'indicator' => $k, 'level' => $level, 'observed_on' => now()->subMonth(), 'source_id' => $src->id, 'methodology' => 'Fixture indicator.']);
        $i->forceFill(['status' => 'verified'])->save();
    }

    return $p;
}

it('creates only flagged demo records that are never verified, and does it once', function () {
    $first = app(DemoDataManager::class)->seed(['Lahore'], 0.2);
    $counts = fn () => [Project::count(), MarketObservation::count(), MarketIndicator::count(), MarketSource::count()];
    $before = $counts();
    $second = app(DemoDataManager::class)->seed(['Lahore'], 0.2);

    expect($first['projects'])->toBeGreaterThan(10)->and($second['projects'])->toBe(0)->and($counts())->toBe($before)
        ->and(Project::where('is_demo', false)->count())->toBe(0)
        // Names read naturally; is_demo is what marks them.
        ->and(Project::where('name', 'like', '%Demo%')->count())->toBe(0)
        ->and(Project::where('is_demo', true)->count())->toBe(Project::count())
        ->and(MarketObservation::where('is_demo', false)->orWhere('status', '!=', 'demo')->count())->toBe(0)
        ->and(MarketObservation::verified()->count())->toBe(0)
        ->and(MarketSource::where('type', 'demo')->count())->toBe(3);
    $p = Project::where('category', 'shopping_mall')->first();
    expect($p->description)->toContain('Illustrative example project — fictional')->and($p->coverUrl())->toContain('images/demo/investments/')
        ->and($p->gallery)->not->toBeEmpty()->and($p->latitude)->not->toBeNull();
});

it('never touches a real project with the same name', function () {
    $name = 'Gulberg Greens Residencia';
    $real = new Project(['name' => $name, 'city' => 'Lahore', 'status' => 'completed', 'description' => 'A real project that happens to share the demo name in this test.']);
    $real->is_published = true;
    $real->save();

    $result = app(DemoDataManager::class)->seed(['Lahore'], 0.2);
    expect($result['skipped_real'])->toBe(1)->and($real->fresh()->is_demo)->toBeFalse()->and($real->fresh()->description)->toContain('real project')
        ->and(MarketObservation::where('project_id', $real->id)->count())->toBe(0);
});

it('ranks demo data with the normal engine in its own snapshot that never replaces the real one', function () {
    realRankedMall();
    $admin = User::factory()->admin()->create();
    $real = app(RankingCalculator::class)->run($admin);
    app(RankingCalculator::class)->publish($real, $admin);
    $demo = demoSeed();

    expect($demo->includes_demo)->toBeTrue()->and($demo->status)->toBe('published')
        ->and($real->fresh()->status)->toBe('published')->and($real->fresh()->includes_demo)->toBeFalse();
    expect(InvestmentRanking::where('calculation_id', $real->id)->where('is_demo', true)->count())->toBe(0)
        ->and(InvestmentRanking::where('calculation_id', $demo->id)->where('is_demo', true)->count())->toBeGreaterThan(0)
        ->and(InvestmentRanking::where('calculation_id', $demo->id)->where('entity_name', 'Real Fixture Mall')->where('is_demo', false)->exists())->toBeTrue();

    // Demo mode on → the demo snapshot; off → only the real one.
    expect(InvestmentCalculation::current()->id)->toBe($demo->id);
    AppSetting::put('investment.demo_mode', false);
    expect(InvestmentCalculation::current()->id)->toBe($real->id);
});

it('fills every property type with scored entries for a city', function () {
    demoSeed(1.0);
    $browser = app(RankingBrowser::class);

    foreach (array_keys(RankingBrowser::segments()) as $segment) {
        $items = $browser->items($segment, 'Lahore');
        expect($items->whereIn('status', ['ranked', 'provisional'])->count())->toBeGreaterThan(0, "No scored entries in {$segment}")
            ->and($items->every(fn ($i) => $i['is_demo']))->toBeTrue();
    }
    expect(Project::where('is_demo', true)->count())->toBe(60);
    $row = InvestmentRanking::where('segment', 'shopping-malls')->ranked()->first();
    expect($row->metric('current.basis_label'))->toStartWith('Illustrative')->not->toContain('Verified')->not->toContain('emo')
        ->and($row->metric('series'))->not->toBeEmpty();
});

it('discloses demo data where it is previewed, and never puts it in the Investment Index', function () {
    demoSeed();
    $notice = config('meezan.investment.demo_notice');

    // The Investment Insights preview still discloses it and stays out of search engines.
    $this->get(route('investment.index'))->assertOk()->assertSee('DEMO DATA', false)->assertSee('Illustrative figures for platform preview only', false)->assertSee('noindex', false);

    // The Investment Index (the public rankings) never reads demo records, even with demo mode on.
    foreach ([route('rankings.index'), route('rankings.category', 'shopping-malls'), route('rankings.overall')] as $url) {
        $page = $this->get($url)->assertOk()->assertDontSee('DEMO DATA')->assertSee('Insufficient Verified Data');
        foreach (Project::where('is_demo', true)->where('category', 'shopping_mall')->limit(5)->pluck('name') as $name) {
            $page->assertDontSee($name);
        }
    }
    $demo = Project::where('is_demo', true)->first();
    $this->get(route('rankings.project', $demo))->assertNotFound();
    expect($this->get(route('sitemap.show', 'pages'))->getContent())->not->toContain('investment-rankings/lahore/commercial');
});

it('hides demo data when demo mode is off and says there is not enough verified data', function () {
    demoSeed();
    AppSetting::put('investment.demo_mode', false);

    $this->get(route('investment.index'))->assertOk()->assertDontSee('DEMO DATA');
    expect(app(RankingBrowser::class)->items('shopping-malls', 'Lahore'))->toBeEmpty();
    $this->get(route('rankings.category', ['shopping-malls', 'city' => 'Lahore']))->assertOk()->assertSee('Insufficient Verified Data');
});

it('answers advisor questions through the Investment Analyst agent and discloses demo data', function () {
    demoSeed();

    $a = app(AdvisorGateway::class)->ask('Which shopping mall is best for investment in Lahore?', null, null, null, null);
    $task = AiTask::where('uuid', $a['task'])->sole();
    expect($a['mode'])->toBe('rules-agent')->and($a['demo'])->toBeTrue()->and($a['answer'][0])->toContain('DEMO DATA')
        ->and($a['items'][0]['is_demo'])->toBeTrue()->and($a['items'][0]['name'])->not->toContain('Demo')
        ->and($task->agent->slug)->toBe('investment-analyst')->and($task->intent)->toBe('advisor')
        ->and($task->toolCalls()->pluck('tool')->all())->toBe(['investment.advisor']);

    $this->getJson(route('rankings.advisor', ['question' => 'Best mall in Lahore?']))->assertOk()
        ->assertJsonPath('demo', true)->assertJsonPath('mode', 'rules-agent')->assertJsonPath('items.0.demo', true);

    // The agent disabled: the panel still answers, directly, and says why.
    AiAgent::where('slug', 'investment-analyst')->update(['status' => 'disabled']);
    $direct = app(AdvisorGateway::class)->ask('Best mall in Lahore?', null, null, null, null);
    expect($direct['mode'])->toBe('rules-direct')->and(implode(' ', $direct['missing']))->toContain('The AI agent was not available');
});

it('routes chat investment questions to the advisor workflow', function () {
    demoSeed();
    $this->postJson(route('ai-finder.match'), ['message' => 'Where should I invest in Lahore?'])->assertOk()
        ->assertJsonPath('intent', 'agent_investment_analyst')->assertJsonPath('reply', fn ($r) => str_contains($r, 'DEMO DATA'));
    expect(AiTask::latest('id')->first()->intent)->toBe('advisor');
});

it('removes demo data only, keeping real records and any demo record a real one uses', function () {
    $real = realRankedMall();
    demoSeed();
    $linked = Project::where('is_demo', true)->first();
    $o = new MarketObservation(['kind' => 'sale', 'price_basis' => 'transaction', 'city' => 'Lahore', 'project_id' => $linked->id, 'asset_class' => 'commercial', 'property_type' => 'shop',
        'currency' => 'PKR', 'average_amount' => 5_000_000, 'observed_on' => now()->subMonth(), 'sample_size' => 3, 'source_id' => MarketSource::where('is_demo', false)->value('id'), 'methodology' => 'A real record on a demo project.']);
    $o->forceFill(['status' => 'draft'])->save();

    $r = app(DemoDataManager::class)->remove();
    expect($r['keptProjects'])->toBe(1)
        ->and(Project::where('is_demo', true)->pluck('id')->all())->toBe([$linked->id])
        ->and(MarketObservation::where('is_demo', true)->count())->toBe(0)
        ->and(MarketIndicator::where('is_demo', true)->count())->toBe(0)
        ->and(InvestmentCalculation::where('includes_demo', true)->count())->toBe(0)
        ->and($real->fresh())->not->toBeNull()
        ->and(MarketObservation::where('project_id', $real->id)->verified()->count())->toBe(5) // four sales and a rent
        ->and($o->fresh())->not->toBeNull();
});

it('gives admins a demo data screen with mode switch, recalculation and guarded removal', function () {
    demoSeed();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.market.demo'))->assertOk()->assertSee('Investment demo data')->assertSee('Remove all demo data')->assertSee('Real data (kept separate)');

    $this->post(route('admin.market.demo.mode'), ['mode' => 'off'])->assertSessionHas('status');
    expect(InvestmentCalculation::demoMode())->toBeFalse();
    $this->post(route('admin.market.demo.mode'), ['mode' => 'default'])->assertSessionHas('status');
    expect(InvestmentCalculation::demoMode())->toBe((bool) config('meezan.investment.demo_mode'));

    $this->post(route('admin.market.demo.run'), ['action' => 'recalculate'])->assertSessionHas('status');
    $this->post(route('admin.market.demo.remove'), ['confirm' => 'yes'])->assertSessionHasErrors('confirm');
    expect(Project::where('is_demo', true)->count())->toBeGreaterThan(0);
    $this->post(route('admin.market.demo.remove'), ['confirm' => 'REMOVE DEMO DATA'])->assertSessionHas('status');
    expect(Project::where('is_demo', true)->count())->toBe(0);

    $this->get(route('admin.market.records.index', ['observations', 'origin' => 'demo']))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('admin.market.demo'))->assertForbidden();
});

it('runs the demo command', function () {
    $this->artisan('meezan:investment-demo mode --off')->assertSuccessful();
    expect(InvestmentCalculation::demoMode())->toBeFalse();
    $this->artisan('meezan:investment-demo status')->assertSuccessful()->expectsOutputToContain('Demo mode: off');
    $this->artisan('meezan:investment-demo remove --force')->assertSuccessful();
});
