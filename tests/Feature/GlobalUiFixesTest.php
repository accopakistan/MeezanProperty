<?php

use App\Models\AppSetting;
use App\Models\MarketObservation;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use App\Services\Investment\DemoDataManager;
use App\Services\Investment\RankingBrowser;

/*
| Global UI fixes: entity names without "Demo" (internal demo flags kept) and
| the same four search tabs — Buy | Rent | Plots | Commercial — everywhere.
*/

/** The tab labels in order, and the selected one, from a page's search tabs. */
function searchTabsOn(string $html): array
{
    preg_match('/<div[^>]*data-search-tabs[^>]*>(.*?)<\/div>/s', $html, $m);
    preg_match_all('/>\s*(Buy|Rent|Plots|Commercial)\s*</', $m[1] ?? '', $labels);
    preg_match('/value="([a-z]+)" class="peer sr-only"\s+checked/', $m[1] ?? '', $checked);
    preg_match('/class="[^"]*bg-brand-600[^"]*"[^>]*aria-current="page"\s*>\s*(\w+)/', $m[1] ?? '', $current);

    return ['labels' => $labels[1], 'active' => $checked[1] ?? strtolower($current[1] ?? '')];
}

/*
| Change 1: entity names
*/

it('gives demo entities natural names and keeps them flagged as demo data', function () {
    AppSetting::put('investment.demo_mode', true); // the admin preview; off by default
    app(DemoDataManager::class)->seed(['Lahore'], 0.2);
    app(DemoDataManager::class)->recalculate(User::factory()->admin()->create());

    expect(Project::where('name', 'like', '%Demo%')->count())->toBe(0)
        ->and(Project::where('is_demo', true)->count())->toBe(Project::count())
        ->and(MarketObservation::where('is_demo', true)->where('status', 'demo')->count())->toBe(MarketObservation::count())
        ->and(Project::where('developer', 'like', '%Demo%')->count())->toBe(0);

    // The demo preview now appears on the Investment Insights pages (the Investment Index never shows demo data).
    $html = $this->get(route('investment.index'))->assertOk()->getContent();
    $visible = strip_tags(preg_replace('/<script.*?<\/script>/s', '', $html));
    expect($visible)->not->toContain('(Demo)')->not->toMatch('/\bDEMO\b(?! DATA)/')
        ->toContain('DEMO DATA')->toContain('The projects shown are fictional examples');
    $mall = Project::where('category', 'shopping_mall')->first();
    $this->get(route('projects.show', $mall))->assertOk()->assertSee('This is a fictional example project')->assertDontSee($mall->name.' (Demo)');
    $this->get(route('rankings.advisor', ['question' => 'Best mall in Lahore?']))->assertJsonPath('demo', true)
        ->assertJsonPath('items.0.name', fn ($n) => ! str_contains($n, 'Demo'));
});

it('renames records from the first dataset version instead of duplicating them', function () {
    app(DemoDataManager::class)->seed(['Lahore'], 0.2);
    $p = Project::where('category', 'shopping_mall')->first();
    $figures = MarketObservation::where('project_id', $p->id)->orderBy('id')->pluck('average_amount')->all();
    $p->forceFill(['name' => $p->name.DemoDataManager::LEGACY_SUFFIX, 'status' => $p->status])->save();
    $count = Project::count();

    $result = app(DemoDataManager::class)->seed(['Lahore'], 0.2);
    $renamed = $p->fresh();
    expect($result['renamed'])->toBe(1)->and($result['projects'])->toBe(0)->and(Project::count())->toBe($count)
        ->and($renamed->name)->not->toContain('(Demo)')->and($renamed->is_demo)->toBeTrue()
        ->and(MarketObservation::where('project_id', $p->id)->orderBy('id')->pluck('average_amount')->all())->toBe($figures);
});

it('keeps admin demo management and demo/verified separation', function () {
    app(DemoDataManager::class)->seed(['Lahore'], 0.2);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.market.demo'))->assertOk()->assertSee('Investment demo data');
    $this->get(route('admin.projects.index', ['origin' => 'demo']))->assertOk()->assertSee('DEMO');
    AppSetting::put('investment.demo_mode', false);
    expect(app(RankingBrowser::class)->items('shopping-malls', 'Lahore'))->toBeEmpty();
});

it('tags sample property listings as samples, not demos', function () {
    $p = Property::factory()->create(['title' => 'Sample Tag House', 'is_demo' => true]);

    $this->get('/properties')->assertSee('Sample listing')->assertDontSee('Demo listing');
    $this->get(route('properties.show', $p->slug))->assertOk()->assertSee('Sample listing')
        ->assertSee('This is not real inventory')->assertDontSee('Demo listing');
    expect($p->fresh()->is_demo)->toBeTrue();
});

it('rewords only the seeded opening line of sample listing descriptions', function () {
    $seeded = Property::factory()->create(['is_demo' => true, 'description' => "Demo listing for local development — this is not real inventory.\n\nA house."]);
    $real = Property::factory()->create(['is_demo' => false, 'description' => 'Demo listing for local development — this is not real inventory.']);

    (require database_path('migrations/2026_11_04_000050_reword_sample_listing_descriptions.php'))->up();

    expect($seeded->fresh()->description)->toBe("Sample listing for platform preview — this is not real inventory.\n\nA house.")
        ->and($real->fresh()->description)->toBe('Demo listing for local development — this is not real inventory.');
});

/*
| Change 2: the four search tabs
*/

it('shows the same four search tabs, in order, on every search page', function (string $url, string $active) {
    $html = $this->get($url)->assertOk()->getContent();
    $tabs = searchTabsOn($html);

    expect($tabs['labels'])->toBe(['Buy', 'Rent', 'Plots', 'Commercial'])->and($tabs['active'])->toBe($active);
})->with([
    'home' => ['/', 'sale'],
    'city page' => ['/lahore', 'sale'],
    'society page' => ['/areas/gulberg-lahore', 'sale'],
    'buy category' => ['/buy', 'sale'],
    'rent category' => ['/rent', 'rent'],
    'plots category' => ['/plots', 'plots'],
    'commercial category' => ['/commercial', 'commercial'],
    'search results (rent)' => ['/properties?tab=rent', 'rent'],
    'search results (plots)' => ['/properties?group=plots', 'plots'],
    'search results (commercial plot)' => ['/properties?tab=commercial&type=commercial_plot', 'commercial'],
    'city listings' => ['/lahore/plots-for-sale', 'plots'],
    'map search' => ['/map?group=commercial', 'commercial'],
    'projects' => ['/projects', 'sale'],
    'investment rankings (Index home)' => ['/investment-rankings', 'sale'],
    'housing societies' => ['/investment-rankings/housing-societies', 'sale'],
    'apartment buildings' => ['/investment-rankings/apartment-buildings?city=Lahore', 'sale'],
    'shopping malls' => ['/investment-rankings/shopping-malls', 'commercial'],
    'commercial plazas' => ['/investment-rankings/commercial-plazas', 'commercial'],
    'business towers' => ['/investment-rankings/business-towers?city=Karachi', 'commercial'],
    'overall index' => ['/investment-rankings/overall', 'sale'],
    'index methodology' => ['/investment-rankings/methodology', 'sale'],
]);

it('shows each tab only its own filters', function () {
    $plots = $this->get('/plots')->getContent();
    // Beds belong to Buy / Rent: hidden and disabled on Plots; size is on.
    expect($plots)->toMatch('/<select name="beds"[^>]*data-search-for="sale rent"[^>]*hidden[^>]*disabled/')
        ->toMatch('/<select name="size"[^>]*data-search-for="plots commercial"(?![^>]*disabled)/');
    $rent = $this->get('/properties?tab=rent')->getContent();
    expect($rent)->toMatch('/aria-label="Monthly rent \(up to\)" data-search-for="rent"(?![^>]*disabled)/');
    $commercial = $this->get('/commercial')->getContent();
    expect($commercial)->toMatch('/<select name="investment"[^>]*data-search-for="commercial"(?![^>]*disabled)/')
        ->toContain('Shopping Malls (rankings)')->toMatch('/<select name="beds"[^>]*disabled/');
});

it('sends each tab to the right results', function () {
    $this->get('/search?tab=sale&location=Gulberg&type=house&beds=3')->assertRedirect('/properties?tab=sale&location=Gulberg&type=house&beds=3');
    $this->get('/search?tab=rent&city=Karachi&max_price=100000')->assertRedirect('/properties?tab=rent&city=Karachi&max_price=100000');
    $this->get('/search?tab=plots&type=house&beds=3&size=5m')->assertRedirect('/properties?tab=plots&size=5m');
    $this->get('/search?tab=commercial&type=commercial_plot&city=Lahore')->assertRedirect('/properties?tab=commercial&city=Lahore&type=commercial_plot');
    $this->get('/search?tab=commercial&city=Lahore&investment=shopping-malls&max_price=20000000&stage=completed')
        ->assertRedirect(route('rankings.category', ['shopping-malls', 'city' => 'Lahore']));
    $this->get('/search?mode=investment&tab=rent&investment_city=lahore')->assertRedirect(route('rankings.category', ['apartment-buildings', 'city' => 'Lahore']));
    // Plots are not ranked by the Investment Index: housing societies are the closest leaderboard.
    $this->get('/search?mode=investment&tab=plots&investment_city=islamabad&size=5-marla')->assertRedirect(route('rankings.category', ['housing-societies', 'city' => 'Islamabad']));
    $this->get('/search?mode=investment&tab=commercial&investment_city=karachi')->assertRedirect(route('rankings.index'));
    $this->get('/search?tab=elsewhere')->assertSessionHasErrors('tab');
});

it('finds the right listings for each tab', function () {
    $house = Property::factory()->create(['title' => 'Tab Test House', 'type' => 'house', 'purpose' => 'sale']);
    $flat = Property::factory()->create(['title' => 'Tab Test Rental Flat', 'type' => 'apartment', 'purpose' => 'rent']);
    $plot = Property::factory()->create(['title' => 'Tab Test Plot', 'type' => 'residential_plot', 'purpose' => 'sale']);
    $shop = Property::factory()->create(['title' => 'Tab Test Shop', 'type' => 'shop', 'purpose' => 'sale']);
    $cplot = Property::factory()->create(['title' => 'Tab Test Commercial Plot', 'type' => 'commercial_plot', 'purpose' => 'sale']);

    $this->get('/properties?tab=sale')->assertSee('Tab Test House')->assertDontSee('Tab Test Rental Flat');
    $this->get('/properties?tab=rent')->assertSee('Tab Test Rental Flat')->assertDontSee('Tab Test House');
    $this->get('/properties?tab=plots')->assertSee('Tab Test Plot')->assertDontSee('Tab Test Shop');
    $this->get('/properties?tab=commercial')->assertSee('Tab Test Shop')->assertDontSee('Tab Test House');
    $this->get('/properties?tab=commercial&type=commercial_plot')->assertSee('Tab Test Commercial Plot')->assertDontSee('Tab Test Shop');
});
