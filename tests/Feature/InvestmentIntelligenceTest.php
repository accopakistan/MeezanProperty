<?php

use App\Enums\DealerStatus;
use App\Models\AuditLog;
use App\Models\InvestmentCalculation;
use App\Models\InvestmentMethodology;
use App\Models\InvestmentRanking;
use App\Models\MarketIndicator;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use App\Services\Investment\InvestmentInsights;
use App\Services\Investment\MarketMetrics;
use App\Services\Investment\MarketRecords;
use App\Services\Investment\RankingCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/*
| Phase 14: investment intelligence. All market figures below are test
| fixtures created in the test database only.
*/

function marketSource(array $attributes = []): MarketSource
{
    return MarketSource::create($attributes + ['name' => 'Test Registry '.uniqid(), 'type' => 'verified_transactions', 'reliability' => 'high', 'is_active' => true]);
}

/** A verified observation (workflow skipped for fixtures). */
function observation(array $attributes = []): MarketObservation
{
    $o = new MarketObservation($attributes + [
        'kind' => 'sale', 'price_basis' => 'transaction', 'city' => 'Lahore', 'asset_class' => 'residential', 'property_type' => 'house',
        'size_value' => 10, 'size_unit' => 'marla', 'currency' => 'PKR', 'average_amount' => 30_000_000, 'observed_on' => now()->subMonth(),
        'sample_size' => 12, 'source_id' => $attributes['source_id'] ?? marketSource()->id, 'methodology' => 'Fixture observation for tests.',
    ]);
    $o->forceFill(['status' => $attributes['status'] ?? 'verified'])->save();

    return $o;
}

function indicator(string $kind, ?string $level, array $attributes = []): MarketIndicator
{
    $i = new MarketIndicator($attributes + [
        'city' => 'Lahore', 'indicator' => $kind, 'level' => $level, 'observed_on' => now()->subMonth(),
        'source_id' => $attributes['source_id'] ?? marketSource()->id, 'methodology' => 'Fixture indicator for tests.',
    ]);
    $i->forceFill(['status' => $attributes['status'] ?? 'verified'])->save();

    return $i;
}

/** A well-documented area: three years of transactions, rent and a full set of indicators. */
function documentedArea(string $areaName = 'Gulberg', array $prices = [20_000_000, 24_000_000, 27_000_000, 30_000_000], string $legal = 'verified'): void
{
    $a = area($areaName);
    $sources = [marketSource(['publisher' => 'Registry A']), marketSource(['publisher' => 'Bureau B']), marketSource(['publisher' => 'Survey C'])];
    foreach ($prices as $i => $price) {
        observation(['area_id' => $a->id, 'city' => $a->city, 'average_amount' => $price, 'min_amount' => (int) ($price * 0.9), 'max_amount' => (int) ($price * 1.1),
            'observed_on' => now()->subMonths(1 + 12 * (count($prices) - 1 - $i)), 'source_id' => $sources[$i % 3]->id]);
    }
    observation(['area_id' => $a->id, 'city' => $a->city, 'kind' => 'rent', 'price_basis' => 'transaction', 'average_amount' => 150_000, 'observed_on' => now()->subMonths(2), 'source_id' => $sources[1]->id]);
    foreach (['demand' => 'high', 'supply' => 'balanced', 'liquidity' => 'high', 'infrastructure' => 'good', 'growth_drivers' => 'strong', 'legal_verification' => $legal] as $kind => $level) {
        indicator($kind, $level, ['area_id' => $a->id, 'city' => $a->city, 'source_id' => $sources[2]->id]);
    }
    indicator('development_progress', null, ['area_id' => $a->id, 'city' => $a->city, 'numeric_value' => 100, 'source_id' => $sources[2]->id]);
}

function calculateAndPublish(): InvestmentCalculation
{
    $calc = app(RankingCalculator::class)->run(User::factory()->admin()->create());
    app(RankingCalculator::class)->publish($calc, User::factory()->admin()->create());

    return $calc->fresh();
}

function rankingFor(string $areaName, string $category = 'overall'): ?InvestmentRanking
{
    return InvestmentRanking::where('entity_type', 'area')->where('entity_id', area($areaName)->id)->where('category', $category)->latest('id')->first();
}

// ── Methodology ──────────────────────────────────────────────────────────

it('seeds methodology v1 with every category weighted to 100', function () {
    $m = InvestmentMethodology::active();

    expect($m->version)->toBe(1);
    foreach (array_keys(config('meezan.investment.categories')) as $category) {
        expect(array_sum($m->weightsFor($category)))->toBe(100);
    }
    expect($m->weightsFor('overall'))->toBe(['price_performance' => 20, 'demand_supply' => 12, 'rental_yield' => 12, 'location_infrastructure' => 12, 'development_progress' => 10, 'liquidity' => 10, 'risk' => 14, 'growth' => 10]);
});

it('rejects weights that do not add up to 100 or are not whole numbers', function () {
    $weights = config('meezan.investment.default_weights');
    $weights['rental']['rental_yield'] = 50;
    expect(fn () => InvestmentMethodology::validateWeights($weights))->toThrow(ValidationException::class, 'add up to 105');

    $weights = config('meezan.investment.default_weights');
    $weights['overall']['risk'] = 'x';
    expect(fn () => InvestmentMethodology::validateWeights($weights))->toThrow(ValidationException::class);
    expect(InvestmentMethodology::validateWeights(config('meezan.investment.default_weights')))->toHaveCount(7);
});

it('lets admins version the methodology without touching active versions', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.market.methodologies.store'))->assertRedirect();
    $draft = InvestmentMethodology::where('version', 2)->firstOrFail();

    $weights = config('meezan.investment.default_weights');
    $weights['overall'] = ['price_performance' => 30, 'demand_supply' => 10, 'rental_yield' => 10, 'location_infrastructure' => 10, 'development_progress' => 10, 'liquidity' => 10, 'risk' => 10, 'growth' => 10];
    $this->actingAs($admin)->patch(route('admin.market.methodologies.update', $draft), ['name' => 'More weight on history', 'weights' => $weights])->assertSessionHasNoErrors();
    $bad = $weights;
    $bad['overall']['risk'] = 20;
    $this->actingAs($admin)->patch(route('admin.market.methodologies.update', $draft), ['name' => 'Bad', 'weights' => $bad])->assertSessionHasErrors('weights.overall');

    $this->actingAs($admin)->post(route('admin.market.methodologies.activate', $draft))->assertRedirect();
    expect(InvestmentMethodology::active()->version)->toBe(2)
        ->and(InvestmentMethodology::where('version', 1)->value('status'))->toBe('retired');
    $this->actingAs($admin)->patch(route('admin.market.methodologies.update', $draft), ['name' => 'x', 'weights' => $weights])->assertStatus(409);
});

// ── Formulas ─────────────────────────────────────────────────────────────

it('calculates price changes only between comparable observations', function () {
    $src = marketSource();
    $series = collect([
        observation(['average_amount' => 20_000_000, 'observed_on' => now()->subMonths(25), 'source_id' => $src->id]),
        observation(['average_amount' => 25_000_000, 'observed_on' => now()->subMonths(13), 'source_id' => $src->id]),
        observation(['average_amount' => 30_000_000, 'observed_on' => now()->subMonth(), 'source_id' => $src->id]),
    ]);

    $one = MarketMetrics::change($series, [9, 15], 12);
    $two = MarketMetrics::change($series, [21, 27], 24);
    expect($one['change'])->toBe(20.0)     // (30 - 25) / 25 × 100
        ->and($two['change'])->toBe(50.0)  // (30 - 20) / 20 × 100
        ->and(MarketMetrics::annualise(50, 24))->toBe(22.47);

    // A different size or basis is a different series, so nothing is compared.
    $mixed = collect([
        observation(['average_amount' => 20_000_000, 'observed_on' => now()->subMonths(13), 'price_basis' => 'asking', 'source_id' => $src->id]),
        observation(['average_amount' => 30_000_000, 'observed_on' => now()->subMonth(), 'size_value' => 1, 'size_unit' => 'kanal', 'source_id' => $src->id]),
    ]);
    $chosen = MarketMetrics::comparableSeries($mixed);
    expect($chosen)->toHaveCount(1)->and(MarketMetrics::change($chosen, [9, 15], 12))->toBeNull();
});

it('calculates gross and net rental yield with the documented formulas', function () {
    expect(MarketMetrics::yieldPercent(150_000, 30_000_000))->toBe(6.0)          // 150,000 × 12 / 30,000,000 × 100
        ->and(MarketMetrics::netYieldPercent(150_000, 300_000, 30_000_000))->toBe(5.0)
        ->and(MarketMetrics::normalise(6, 2, 10))->toBe(50.0)
        ->and(MarketMetrics::normalise(40, 2, 10))->toBe(100.0);

    $this->get(route('investment.yield', ['price' => 30_000_000, 'rent' => 150_000, 'costs' => 300_000]))->assertOk()
        ->assertSee('6%')->assertSee('5%')->assertSee('Estimated gross yield')->assertSee('not a net return');
});

// ── Calculation, scoring, confidence, risk ───────────────────────────────

it('ranks a well-documented area from verified data with transparent metrics', function () {
    documentedArea('Gulberg');
    calculateAndPublish();

    $r = rankingFor('Gulberg');
    expect($r->status)->toBe('ranked')
        ->and($r->score)->toBeGreaterThan(0)->toBeLessThanOrEqual(100)
        ->and($r->rank)->toBe(1)
        ->and($r->methodology_version)->toBe(1)
        ->and($r->metric('change_1y.percent'))->toBe(11.11)   // 27m -> 30m
        ->and($r->metric('change_2y.percent'))->toEqual(25)    // 24m -> 30m
        ->and($r->metric('yield.gross'))->toEqual(6)
        ->and($r->metric('legal.status'))->toBe('verified')
        ->and($r->risk)->toBeIn(['low', 'medium'])
        ->and($r->metric('scenarios.available'))->toBeTrue()
        ->and($r->metric('scenarios.rates.conservative'))->toBeLessThanOrEqual($r->metric('scenarios.rates.high'))
        ->and(collect($r->factors['items'])->every(fn ($f) => array_key_exists('basis', $f)))->toBeTrue();
});

it('never gives missing data a neutral score', function () {
    // Only prices: 20% of the overall weight has data.
    $a = area('Model Town');
    $src = marketSource();
    observation(['area_id' => $a->id, 'average_amount' => 20_000_000, 'observed_on' => now()->subMonths(13), 'source_id' => $src->id]);
    observation(['area_id' => $a->id, 'average_amount' => 22_000_000, 'observed_on' => now()->subMonth(), 'source_id' => $src->id]);
    calculateAndPublish();

    $r = rankingFor('Model Town');
    expect($r->status)->toBe('insufficient_data')->and($r->score)->toBeNull()
        ->and($r->confidence)->toBe('insufficient')
        ->and($r->factors['reason'])->toContain('at least 70%')
        ->and($r->factors['items']['rental_yield']['score'])->toBeNull()
        ->and(rankingFor('Model Town', 'rental')->factors['reason'])->toContain('Rental yield');
});

it('keeps data confidence separate from the score', function () {
    // Strong indicators but a single low-reliability source and few observations.
    $a = area('Johar Town');
    $src = marketSource(['reliability' => 'low', 'type' => 'public_asking_prices']);
    observation(['area_id' => $a->id, 'price_basis' => 'asking', 'average_amount' => 10_000_000, 'observed_on' => now()->subMonths(13), 'source_id' => $src->id]);
    observation(['area_id' => $a->id, 'price_basis' => 'asking', 'average_amount' => 13_000_000, 'observed_on' => now()->subMonth(), 'source_id' => $src->id]);
    foreach (['demand' => 'very_high', 'supply' => 'limited', 'liquidity' => 'high', 'infrastructure' => 'excellent', 'growth_drivers' => 'strong', 'legal_verification' => 'verified'] as $kind => $level) {
        indicator($kind, $level, ['area_id' => $a->id, 'source_id' => $src->id]);
    }
    indicator('development_progress', null, ['area_id' => $a->id, 'numeric_value' => 100, 'source_id' => $src->id]);
    calculateAndPublish();

    $r = rankingFor('Johar Town');
    expect($r->status)->toBe('ranked')->and($r->score)->toBeGreaterThan(60)
        ->and($r->confidence)->toBeIn(['limited', 'medium'])->not->toBe('high')
        ->and(implode(' ', $r->metric('confidence.reasons')))->toContain('asking prices, not completed transactions')->toContain('1 independent source');
});

it('never classifies an unverified entry as low risk and needs enough evidence to classify', function () {
    documentedArea('Bahria Town', legal: 'unverified');
    $sparse = area('Lake City');
    indicator('demand', 'high', ['area_id' => $sparse->id]);
    calculateAndPublish();

    $r = rankingFor('Bahria Town');
    expect($r->risk)->not->toBe('low')
        ->and(implode(' ', $r->metric('risk.warnings')))->toContain('Legal verification is unverified');
    expect(rankingFor('Lake City')->risk)->toBe('insufficient');

    $this->get(route('investment.show', ['area', area('Bahria Town')->id]))->assertOk()->assertSee('Verification warning');
});

it('offers no numerical scenario without two years of comparable history', function () {
    $a = area('DHA Phase 6');
    $src = marketSource();
    observation(['area_id' => $a->id, 'average_amount' => 40_000_000, 'observed_on' => now()->subMonths(13), 'source_id' => $src->id]);
    observation(['area_id' => $a->id, 'average_amount' => 44_000_000, 'observed_on' => now()->subMonth(), 'source_id' => $src->id]);
    calculateAndPublish();

    $r = rankingFor('DHA Phase 6');
    expect($r->metric('scenarios.available'))->toBeFalse()->and($r->metric('scenarios.reason'))->toContain('comparable');
    $this->get(route('investment.show', ['area', $a->id]))->assertSee('No numerical scenario');
});

it('ranks emerging investments only when they are still developing', function () {
    $project = Project::create(['name' => 'Test Heights', 'city' => 'Lahore', 'status' => 'under_construction', 'developer' => 'Test Developer', 'description' => 'Test project used only in the test database.']);
    $src = marketSource(['type' => 'official_record']);
    foreach (['demand' => 'high', 'infrastructure' => 'good', 'growth_drivers' => 'moderate', 'legal_verification' => 'partially_verified', 'developer_status' => 'on_track', 'supply' => 'balanced'] as $kind => $level) {
        indicator($kind, $level, ['project_id' => $project->id, 'source_id' => $src->id]);
    }
    indicator('development_progress', null, ['project_id' => $project->id, 'numeric_value' => 45, 'source_id' => $src->id]);
    documentedArea('Gulberg');
    calculateAndPublish();

    expect(InvestmentRanking::where(['entity_type' => 'project', 'entity_id' => $project->id, 'category' => 'emerging'])->value('status'))->toBe('ranked')
        ->and(rankingFor('Gulberg', 'emerging'))->toBeNull();
});

it('uses only verified records in calculations', function () {
    $a = area('Wapda Town');
    $src = marketSource();
    observation(['area_id' => $a->id, 'average_amount' => 10_000_000, 'observed_on' => now()->subMonths(13), 'source_id' => $src->id, 'status' => 'reviewed']);
    observation(['area_id' => $a->id, 'average_amount' => 50_000_000, 'observed_on' => now()->subMonth(), 'source_id' => $src->id, 'status' => 'draft']);
    $calc = app(RankingCalculator::class)->run();

    expect($calc->summary['observations'])->toBe(0)->and(rankingFor('Wapda Town'))->toBeNull();
});

// ── Publication and history ──────────────────────────────────────────────

it('never publishes automatically and keeps earlier snapshots unchanged', function () {
    documentedArea('Gulberg');
    $this->artisan('meezan:investment-recalculate')->assertSuccessful();
    expect(InvestmentCalculation::sole()->status)->toBe('completed')->and(InvestmentCalculation::current())->toBeNull();
    $this->get(route('investment.index'))->assertOk()->assertSee('No published rankings yet');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.market.calculations.publish', InvestmentCalculation::sole()))->assertRedirect();
    $first = InvestmentCalculation::current();
    $firstScore = rankingFor('Gulberg')->score;

    // New data and a new run: the old publication becomes history, untouched.
    observation(['area_id' => area('Gulberg')->id, 'average_amount' => 36_000_000, 'observed_on' => now()->subDays(3)]);
    $this->actingAs($admin)->post(route('admin.market.calculations.store'))->assertRedirect();
    $second = InvestmentCalculation::latest('id')->first();
    $this->actingAs($admin)->post(route('admin.market.calculations.publish', $second));

    expect($first->fresh()->status)->toBe('superseded')
        ->and($first->rankings()->where('entity_id', area('Gulberg')->id)->where('category', 'overall')->value('score'))->toBe($firstScore)
        ->and(InvestmentCalculation::current()->id)->toBe($second->id);
    expect(fn () => $first->rankings()->first()->update(['score' => 1]))->toThrow(LogicException::class);
    $this->actingAs($admin)->post(route('admin.market.calculations.publish', $first))->assertSessionHasErrors('publish');

    $this->get(route('investment.show', ['area', area('Gulberg')->id]))->assertOk()->assertSee('Published history');
});

// ── Data Centre: entry, workflow, import ─────────────────────────────────

it('takes observations through draft, review and verification', function () {
    $admin = User::factory()->admin()->create();
    $src = marketSource();
    $payload = ['kind' => 'sale', 'price_basis' => 'transaction', 'city' => 'Karachi', 'area_slug' => '', 'asset_class' => 'residential', 'property_type' => 'apartment',
        'size_value' => 1200, 'size_unit' => 'sqft', 'currency' => 'PKR', 'average_amount' => 18_000_000, 'observed_on' => now()->subMonth()->toDateString(),
        'sample_size' => 8, 'source_id' => $src->id, 'methodology' => 'Average of registered sale deeds for the month.'];

    $this->actingAs($admin)->post(route('admin.market.records.store', 'observations'), $payload)->assertSessionHasNoErrors();
    $o = MarketObservation::sole();
    expect($o->status)->toBe('draft')->and($o->size_sqft)->toBe(1200.0);

    // Same record again is a duplicate.
    $this->actingAs($admin)->post(route('admin.market.records.store', 'observations'), $payload)->assertSessionHasErrors('observed_on');

    $go = fn (string $action, array $extra = []) => $this->actingAs($admin)->patch(route('admin.market.records.transition', ['observations', $o->id]), ['action' => $action] + $extra);
    $go('verify')->assertSessionHasErrors('action');     // cannot skip review
    $go('submit');
    $go('reject')->assertSessionHasErrors('reason');     // a reason is required
    $go('reject', ['reason' => 'Sample too small'])->assertSessionHasNoErrors();
    expect($o->fresh()->status)->toBe('rejected');
    $go('submit');
    $go('review');
    $go('verify');
    expect($o->fresh())->status->toBe('verified')->verified_by->toBe($admin->id);

    $this->actingAs($admin)->patch(route('admin.market.records.update', ['observations', $o->id]), $payload + ['average_amount' => 1])->assertStatus(409);
    expect(AuditLog::where('action', 'like', 'market.observation_%')->count())->toBeGreaterThanOrEqual(6);
});

it('validates market records strictly', function () {
    $asking = marketSource(['type' => 'public_asking_prices']);
    $base = ['kind' => 'sale', 'price_basis' => 'asking', 'city' => 'Lahore', 'asset_class' => 'residential', 'property_type' => 'house', 'currency' => 'PKR',
        'average_amount' => 10_000_000, 'observed_on' => now()->toDateString(), 'sample_size' => 3, 'source_id' => $asking->id, 'methodology' => 'Average of public asking prices.'];

    expect(fn () => MarketRecords::validateObservation(['price_basis' => 'transaction'] + $base))->toThrow(ValidationException::class, 'asking-price source')
        ->and(fn () => MarketRecords::validateObservation(['asset_class' => 'commercial'] + $base))->toThrow(ValidationException::class, 'asset class')
        ->and(fn () => MarketRecords::validateObservation(['city' => 'Dubai'] + $base))->toThrow(ValidationException::class)
        ->and(fn () => MarketRecords::validateObservation(['area_slug' => area('Gulberg')->slug, 'city' => 'Karachi'] + $base))->toThrow(ValidationException::class)
        ->and(fn () => MarketRecords::validateObservation(['observed_on' => now()->addDay()->toDateString()] + $base))->toThrow(ValidationException::class)
        ->and(fn () => MarketRecords::validateObservation(['min_amount' => 20_000_000] + $base))->toThrow(ValidationException::class)
        ->and(fn () => MarketRecords::validateIndicator(['city' => 'Lahore', 'indicator' => 'demand', 'level' => 'enormous', 'observed_on' => now()->toDateString(), 'source_id' => $asking->id, 'methodology' => 'Survey of dealers in the area.']))->toThrow(ValidationException::class);

    // All seven launch cities are accepted.
    foreach (config('meezan.cities') as $city) {
        expect(MarketRecords::validateObservation(['city' => $city] + $base)['city'])->toBe($city);
    }
});

it('imports CSV rows as drafts, skipping duplicates and reporting row errors', function () {
    $admin = User::factory()->admin()->create();
    $src = marketSource();
    $header = implode(',', MarketRecords::OBSERVATION_COLUMNS);
    $row = fn (int $amount, string $city = 'Islamabad') => "sale,transaction,{$city},,,residential,house,10,marla,PKR,{$amount},,,".now()->subMonth()->toDateString().",5,{$src->id},Registered transfers in the sector,";
    $csv = implode("\n", [$header, $row(25_000_000), $row(25_000_000), $row(26_000_000, 'Multan'), "sale,transaction,Nowhere,,,residential,house,10,marla,PKR,1,,,2020-01-01,5,{$src->id},Bad city row,"]);
    observation(['city' => 'Islamabad', 'average_amount' => 26_000_000, 'observed_on' => now()->subMonth(), 'source_id' => $src->id, 'size_value' => 10, 'size_unit' => 'marla']);

    $this->actingAs($admin)->post(route('admin.market.records.import.store', 'observations'), ['file' => UploadedFile::fake()->createWithContent('prices.csv', $csv)])
        ->assertOk()->assertSeeText('2 imported as drafts')->assertSeeText('1 duplicates skipped')->assertSeeText('Row 5: The selected city is invalid.');

    expect(MarketObservation::where('status', 'draft')->count())->toBe(2);

    $this->actingAs($admin)->post(route('admin.market.records.import.store', 'observations'), ['file' => UploadedFile::fake()->createWithContent('bad.csv', "city,observed_on,source_id,methodology,price_secret\nLahore,2026-01-01,1,x,1")])
        ->assertSessionHasErrors('file');
    $this->actingAs($admin)->post(route('admin.market.records.import.store', 'observations'), ['file' => UploadedFile::fake()->create('prices.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');
});

// ── Authorization and privacy ────────────────────────────────────────────

it('keeps the Data Centre to admins', function () {
    $o = observation(['status' => 'submitted']);
    $this->get(route('admin.market.index'))->assertRedirect(route('login'));

    foreach ([User::factory()->create(), User::factory()->dealer(DealerStatus::Verified)->create()] as $user) {
        $this->actingAs($user)->get(route('admin.market.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.market.calculations.store'))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.market.records.transition', ['observations', $o->id]), ['action' => 'review'])->assertForbidden();
        $this->actingAs($user)->post(route('admin.market.sources.store'), ['name' => 'X', 'type' => 'admin_research', 'reliability' => 'high'])->assertForbidden();
    }
    expect($o->fresh()->status)->toBe('submitted')->and(InvestmentCalculation::count())->toBe(0);
});

it('shows only published, verified information publicly and nothing about users', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create(['email' => 'private-dealer@example.com', 'phone' => '03001234567']);
    documentedArea('Gulberg');
    observation(['area_id' => area('Gulberg')->id, 'average_amount' => 99_999_999, 'status' => 'draft', 'observed_on' => now()]);
    calculateAndPublish();

    foreach ([route('investment.index'), route('investment.show', ['area', area('Gulberg')->id]), route('investment.compare', ['items' => ['area:'.area('Gulberg')->id]])] as $url) {
        $this->get($url)->assertOk()->assertDontSee('private-dealer@example.com')->assertDontSee('03001234567')->assertDontSee('99,999,999')->assertSee(config('meezan.investment.disclaimer'));
    }
    $this->get(route('investment.index', ['city' => 'Lahore']))->assertSee('Gulberg')->assertSee('#1');
    $this->get(route('investment.index', ['city' => 'Karachi']))->assertSee('Nothing matches these filters')->assertDontSee('Rank 1');
});

// ── Comparison and insights ──────────────────────────────────────────────

it('highlights winners only when every compared entry has the data', function () {
    documentedArea('Gulberg', [20_000_000, 24_000_000, 27_000_000, 30_000_000]);
    documentedArea('DHA Phase 5', [30_000_000, 33_000_000, 35_000_000, 36_000_000]);
    calculateAndPublish();
    $items = ['area:'.area('Gulberg')->id, 'area:'.area('DHA Phase 5')->id];

    $this->get(route('investment.compare', ['items' => $items]))->assertOk()->assertSee('Highest growth')->assertSee('Best yield');

    // Without comparable yield data for one entry there is no yield winner.
    documentedArea('Model Town', [10_000_000, 12_000_000, 13_000_000, 14_000_000]);
    MarketObservation::where('area_id', area('Model Town')->id)->where('kind', 'rent')->update(['status' => 'rejected']);
    calculateAndPublish();
    $this->get(route('investment.compare', ['items' => [$items[0], 'area:'.area('Model Town')->id]]))->assertOk()->assertDontSee('Best yield');
});

it('grounds insights in the snapshot and rejects AI text with invented numbers', function () {
    documentedArea('Gulberg');
    calculateAndPublish();
    $r = rankingFor('Gulberg');

    $rules = app(InvestmentInsights::class)->for($r);
    expect($rules['interpretation_source'])->toBe('rules')
        ->and(implode(' ', $rules['verified']))->toContain('Latest Verified transaction')
        ->and(implode(' ', $rules['calculated']))->toContain('+11.11%')
        ->and($rules['scenarios'][0])->toContain('not a forecast');

    config(['meezan.investment.ai_driver' => 'llm', 'services.openai.key' => 'test-key']);
    Http::fake(['*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => 'Prices will rise 45% next year.']]]])
        ->push(['choices' => [['message' => ['content' => 'Verified transactions show an 11.11% rise over one year; rental data supports a gross yield of 6%.']]]])]);
    expect(app(InvestmentInsights::class)->for($r)['interpretation_source'])->toBe('rules');

    $grounded = app(InvestmentInsights::class)->for($r);
    expect($grounded['interpretation_source'])->toBe('llm');
    Http::assertSent(fn ($request) => ! str_contains($request->body(), '@') && str_contains($request->body(), 'Gulberg'));
});

it('lists all seven cities and the disclaimer on the ranking page', function () {
    $html = $this->get(route('investment.index'))->assertOk()->assertSee(config('meezan.investment.disclaimer'))->getContent();
    foreach (config('meezan.cities') as $city) {
        expect($html)->toContain('<option value="'.$city.'"');
    }
    $this->get(route('investment.methodology'))->assertOk()->assertSee('Weights · methodology v1')->assertSee('Planned — no data yet');
});
