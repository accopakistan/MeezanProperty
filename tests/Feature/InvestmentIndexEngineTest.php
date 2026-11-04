<?php

use App\Models\InvestmentCalculation;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexChange;
use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Services\InvestmentIndex\IndexEngine;
use Illuminate\Validation\ValidationException;

/*
| Meezan Investment Index, Phase 1: five categories ranked from verified,
| traceable evidence only, with the legal gate, Data Confidence Score, risk
| classes, Overall Index, automatic publishing and change history.
*/

function indexSource(string $type, string $publisher): MarketSource
{
    $s = MarketSource::create(['name' => "$publisher ($type)", 'type' => $type, 'publisher' => $publisher, 'reliability' => 'high', 'url' => 'https://example.gov.pk/'.Str::slug($publisher)]);
    $s->forceFill(['is_active' => true])->save();

    return $s;
}

function indexProject(string $category, array $attributes = []): Project
{
    $p = new Project(array_merge(['name' => 'Index Test '.Str::random(5), 'category' => $category, 'city' => 'Lahore', 'status' => 'completed',
        'developer' => 'Test Developers', 'description' => 'A real-data fixture used only by the automated tests.'], $attributes));
    $p->forceFill(['is_published' => true, 'is_demo' => $attributes['is_demo'] ?? false, 'is_featured' => $attributes['is_featured'] ?? false])->save();

    return $p;
}

function evidence(Project $p, string $factor, MarketSource $s, float|string $value, array $extra = []): InvestmentEvidence
{
    $e = new InvestmentEvidence(array_merge(['project_id' => $p->id, 'factor' => $factor, 'source_id' => $s->id,
        'value_numeric' => is_string($value) ? null : $value, 'value_level' => is_string($value) ? $value : null,
        'observed_on' => now()->subMonths(2), 'source_url' => $s->url.'/'.$factor, 'collected_at' => now()], $extra));
    $e->forceFill(['verification_status' => 'verified'])->save();

    return $e;
}

function legal(Project $p, MarketSource $s, string $status, string $type = 'approval', array $extra = []): InvestmentLegalRecord
{
    $r = new InvestmentLegalRecord(array_merge(['project_id' => $p->id, 'authority' => 'LDA', 'record_type' => $type, 'status' => $status,
        'issued_on' => now()->subYear(), 'source_id' => $s->id, 'source_url' => $s->url.'/schemes', 'collected_at' => now()], $extra));
    $r->forceFill(['verification_status' => 'verified'])->save();

    return $r;
}

/** Approved, 80% developed, 70% occupied, good location: 70% of the society weight. */
function evidencedSociety(array $attributes = [], float $progress = 80): Project
{
    $official = MarketSource::firstWhere('type', 'official_record') ?? indexSource('official_record', 'Lahore Development Authority');
    $inspector = MarketSource::firstWhere('type', 'site_inspection') ?? indexSource('site_inspection', 'Independent Surveyors');
    $p = indexProject('residential_society', $attributes);
    legal($p, $official, 'approved');
    evidence($p, 'development_progress', $official, $progress);
    evidence($p, 'occupancy', $inspector, 70);
    evidence($p, 'location', $inspector, 'good');

    return $p;
}

function publishedScore(Project $p): ?InvestmentIndexScore
{
    app(IndexEngine::class)->run('test');

    return InvestmentIndexRun::latestPublished()?->scores()->where('project_id', $p->id)->first();
}

it('uses exactly the five categories and the weights from the scope', function () {
    expect(array_keys(config('investment_index.categories')))->toBe(['housing-societies', 'commercial-plazas', 'apartment-buildings', 'shopping-malls', 'business-towers'])
        ->and(InvestmentIndexMethodology::configWeights())->toBe([
            'housing-societies' => ['legal_approval' => 25, 'development_progress' => 20, 'occupancy' => 15, 'market_performance' => 10, 'market_demand' => 10, 'location' => 10, 'utilities' => 5, 'developer_track_record' => 5],
            'commercial-plazas' => ['legal_approval' => 20, 'rental_income' => 20, 'occupancy' => 15, 'footfall' => 15, 'location' => 10, 'tenant_quality' => 10, 'maintenance' => 5, 'market_performance' => 5],
            'apartment-buildings' => ['legal_approval' => 20, 'occupancy' => 15, 'rental_income' => 15, 'structural_safety' => 15, 'developer_track_record' => 10, 'location' => 10, 'maintenance' => 5, 'market_performance' => 5, 'market_demand' => 5],
            'shopping-malls' => ['legal_approval' => 20, 'operational_performance' => 15, 'occupancy' => 15, 'rental_income' => 15, 'footfall' => 10, 'tenant_quality' => 10, 'location' => 5, 'structural_safety' => 5, 'financial_stability' => 5],
            'business-towers' => ['legal_approval' => 20, 'occupancy' => 15, 'rental_income' => 15, 'tenant_quality' => 15, 'location' => 10, 'structural_safety' => 10, 'developer_track_record' => 5, 'maintenance' => 5, 'market_demand' => 5],
        ]);
    foreach (InvestmentIndexMethodology::configWeights() as $weights) {
        expect(array_sum($weights))->toBe(100);
    }
    expect(fn () => InvestmentIndexMethodology::validateWeights(['housing-societies' => ['legal_approval' => 0]]))->toThrow(ValidationException::class);
});

it('assesses only real, published developments in the five categories', function () {
    $society = indexProject('residential_society');
    $office = indexProject('office_building');
    $land = indexProject('commercial_land');
    $mixed = indexProject('mixed_use');
    $demo = indexProject('shopping_mall', ['is_demo' => true]);
    $draft = indexProject('commercial_plaza');
    $draft->forceFill(['is_published' => false])->save();

    app(IndexEngine::class)->run('test');
    $assessed = InvestmentIndexRun::latestPublished()->scores()->pluck('category', 'project_id');

    expect($assessed->keys()->sort()->values()->all())->toBe([$society->id, $office->id])
        ->and($assessed[$office->id])->toBe('business-towers');
});

it('ranks a project from verified evidence and explains every factor', function () {
    $p = evidencedSociety();
    $s = publishedScore($p);

    // (25x100 + 20x80 + 15x70 + 10x75) / 70 = 84.29, less 20% of the 30% weight without evidence.
    expect($s->status)->toBe('ranked')->and($s->score)->toBe(79.2)->and($s->coverage)->toBe(0.7)
        ->and($s->legal_status)->toBe('approved')->and($s->category_rank)->toBe(1)
        ->and($s->development_progress)->toBe(80.0)
        ->and($s->factors['occupancy'])->toMatchArray(['score' => 70.0, 'value' => '70%', 'weight' => 15, 'contribution' => 10.5])
        ->and($s->factors['utilities']['score'])->toBeNull()->and($s->factors['utilities']['missing'])->toBe('No verified assessment on file.')
        ->and($s->confidence_score)->toBeGreaterThan(35)->and($s->source_ids)->toHaveCount(2)
        ->and($s->last_verified_at)->not->toBeNull();
});

it('never treats an unverified legal status as approved', function () {
    $inspector = indexSource('site_inspection', 'Independent Surveyors');
    $p = indexProject('residential_society');
    evidence($p, 'development_progress', $inspector, 90);
    evidence($p, 'occupancy', $inspector, 90);
    evidence($p, 'location', $inspector, 'excellent');
    // An unverified approval does not count.
    $r = new InvestmentLegalRecord(['project_id' => $p->id, 'authority' => 'LDA', 'record_type' => 'approval', 'status' => 'approved',
        'source_url' => 'https://example.gov.pk/x', 'collected_at' => now()]);
    $r->save();

    $s = publishedScore($p);
    expect($s->status)->toBe('insufficient')->and($s->legal_status)->toBe('not_verified')->and($s->score)->toBeNull()
        ->and($s->reasons[0])->toContain('legal status has not been verified')
        ->and($s->factors['legal_approval']['value'])->toBe('Legal status not verified');
});

it('excludes projects with serious legal problems until an official clearance', function () {
    $p = evidencedSociety();
    $official = MarketSource::firstWhere('type', 'official_record');
    legal($p, $official, 'illegal', 'illegal_listing', ['issued_on' => now()->subMonths(3), 'reference' => 'Illegal schemes list 2026']);

    $s = publishedScore($p);
    expect($s->status)->toBe('excluded')->and($s->risk_class)->toBe('excluded')->and($s->score)->toBeNull()
        ->and($s->category_rank)->toBeNull()->and(implode(' ', $s->reasons))->toContain('Illegal schemes list 2026')
        ->and(IndexEngine::leaderboard('housing-societies'))->toBeEmpty();

    legal($p, $official, 'approved', 'regularisation', ['issued_on' => now()->subMonth()]);
    expect(publishedScore($p)->status)->toBe('ranked');

    // A later cancellation excludes it again; so does a serious court finding.
    legal($p, $official, 'cancelled', 'cancellation', ['issued_on' => now()->subDays(5)]);
    expect(publishedScore($p)->status)->toBe('excluded');
});

it('scores but never ranks a project whose approval is only pending', function () {
    $p = evidencedSociety();
    InvestmentLegalRecord::where('project_id', $p->id)->update(['status' => 'pending']);

    $s = publishedScore($p);
    expect($s->status)->toBe('not_eligible')->and($s->score)->not->toBeNull()->and($s->category_rank)->toBeNull()
        ->and($s->risks)->toContain('Approval is still pending.');
});

it('shows Insufficient Verified Data when too little of the weight is verified', function () {
    $official = indexSource('official_record', 'Lahore Development Authority');
    $p = indexProject('shopping_mall');
    legal($p, $official, 'approved');

    $s = publishedScore($p);
    expect($s->status)->toBe('insufficient')->and($s->score)->toBeNull()->and($s->coverage)->toBe(0.2)
        ->and($s->reasons[0])->toContain('covers 20% of the scoring weight')->and($s->reasons[1])->toContain('Verified rental income');
});

it('never treats asking prices as transactions or publishes unsupported yields', function () {
    $p = evidencedSociety();
    $market = indexSource('verified_transactions', 'Registry Data');
    $obs = function (string $kind, string $basis, int $amount, $date) use ($p, $market) {
        $o = new MarketObservation(['kind' => $kind, 'price_basis' => $basis, 'country' => 'Pakistan', 'city' => 'Lahore', 'project_id' => $p->id,
            'asset_class' => 'residential', 'property_type' => 'house', 'size_value' => 10, 'size_unit' => 'marla', 'currency' => 'PKR',
            'average_amount' => $amount, 'observed_on' => $date, 'sample_size' => 5, 'source_id' => $market->id, 'methodology' => 'Registry extract (test fixture).']);
        $o->forceFill(['status' => 'verified'])->save();
    };
    $obs('sale', 'asking', 20_000_000, now()->subMonths(20));
    $obs('sale', 'asking', 30_000_000, now()->subMonths(2));
    $obs('rent', 'asking', 150_000, now()->subMonths(2));

    $s = publishedScore($p);
    expect($s->factors['market_performance']['score'])->toBeNull()->and($s->verified_yield)->toBeNull();

    $obs('sale', 'transaction', 20_000_000, now()->subMonths(14));
    $obs('sale', 'transaction', 22_000_000, now()->subMonths(2));
    $obs('rent', 'transaction', 100_000, now()->subMonths(2));
    $s = publishedScore($p);
    // 20m -> 22m over 12 months = +10% a year; 100k x 12 / 22m = 5.45% gross.
    expect($s->factors['market_performance']['value'])->toBe('+10% a year')->and($s->verified_yield)->toBe(5.45);
});

it('refuses to verify evidence without real, traceable provenance', function () {
    $p = indexProject('commercial_plaza');
    $research = indexSource('admin_research', 'Meezan Research');
    $asking = indexSource('public_asking_prices', 'Portal');
    $audited = indexSource('audited_document', 'Chartered Accountants');

    expect(fn () => evidence($p, 'rental_income', $research, 7.5))->toThrow(DomainException::class, 'not accepted')
        ->and(fn () => evidence($p, 'occupancy', $asking, 90))->toThrow(DomainException::class, 'cannot verify')
        ->and(fn () => evidence($p, 'rental_income', $audited, 7.5, ['source_url' => 'not a url']))->toThrow(DomainException::class, 'source URL')
        ->and(fn () => evidence(indexProject('commercial_plaza', ['is_demo' => true]), 'rental_income', $audited, 7.5))->toThrow(DomainException::class, 'demo')
        ->and(evidence($p, 'rental_income', $audited, 7.5)->verification_status)->toBe('verified');
});

it('is not affected by featured or paid placement', function () {
    $strong = evidencedSociety(progress: 95);
    $weak = evidencedSociety(['is_featured' => true], progress: 40);
    $third = evidencedSociety(progress: 60);
    app(IndexEngine::class)->run('test');
    $before = IndexEngine::leaderboard('housing-societies')->pluck('project_id')->all();

    $weak->forceFill(['is_featured' => true])->save();
    $strong->forceFill(['is_featured' => false])->save();
    app(IndexEngine::class)->run('test');

    expect($before)->toBe([$strong->id, $third->id, $weak->id])
        ->and(IndexEngine::leaderboard('housing-societies')->pluck('project_id')->all())->toBe($before);
});

it('builds the Overall Index from normalized scores and confidence, only with enough peers', function () {
    $a = evidencedSociety(progress: 95);
    $b = evidencedSociety(progress: 60);
    $c = evidencedSociety(progress: 40);
    $official = MarketSource::firstWhere('type', 'official_record');
    $mall = indexProject('shopping_mall');
    legal($mall, $official, 'approved');
    foreach (['operational_performance' => 'strong', 'tenant_quality' => 'good'] as $f => $v) {
        evidence($mall, $f, indexSource('audited_document', 'Auditors '.$f), $v);
    }
    evidence($mall, 'occupancy', $official, 92);
    evidence($mall, 'rental_income', indexSource('audited_document', 'Rent Roll Auditors'), 8.0);

    app(IndexEngine::class)->run('test');
    $scores = InvestmentIndexRun::latestPublished()->scores()->get()->keyBy('project_id');

    // Three ranked societies: mid-rank percentiles 83.3, 50, 16.7, scaled by confidence.
    expect($scores[$a->id]->normalized_score)->toBe(83.3)->and($scores[$b->id]->normalized_score)->toBe(50.0)
        ->and($scores[$a->id]->overall_index)->toBe(round(83.3 * (0.5 + 0.5 * $scores[$a->id]->confidence_score / 100), 1))
        ->and($scores[$a->id]->overall_rank)->toBe(1)->and($scores[$c->id]->overall_rank)->toBe(3)
        // One ranked mall is too few peers to normalise: ranked in its category, not in the Overall Index.
        ->and($scores[$mall->id]->status)->toBe('ranked')->and($scores[$mall->id]->category_rank)->toBe(1)
        ->and($scores[$mall->id]->overall_rank)->toBeNull();
});

it('recalculates and publishes automatically when verified data changes', function () {
    $p = evidencedSociety();

    // No manual run: saving verified evidence queued the recalculation, which published.
    $run = InvestmentIndexRun::latestPublished();
    expect($run)->not->toBeNull()->and($run->trigger)->toBe('evidence')
        ->and($run->scores()->where('project_id', $p->id)->value('status'))->toBe('ranked');

    // Nothing changed: recorded as unchanged, the published run stays.
    expect(app(IndexEngine::class)->run('test')->status)->toBe('unchanged')
        ->and(InvestmentIndexRun::latestPublished()->id)->toBe($run->id);
});

it('keeps a ranking change history with reasons', function () {
    $a = evidencedSociety(progress: 90);
    $b = evidencedSociety(progress: 50);
    $c = evidencedSociety(progress: 70);
    app(IndexEngine::class)->run('test');
    expect(IndexEngine::leaderboard('housing-societies')->pluck('project_id')->all())->toBe([$a->id, $c->id, $b->id]);

    evidence($b, 'development_progress', MarketSource::firstWhere('type', 'official_record'), 100, ['observed_on' => now()->subWeek()]);
    $change = InvestmentIndexChange::where('project_id', $b->id)->latest('id')->first();

    expect($change->change)->toBe('moved_up')->and($change->from_rank)->toBe(3)->and($change->to_rank)->toBe(1)
        ->and(implode(' ', $change->reasons))->toContain('Development progress: 50 (50%) → 100 (100%)')
        ->and(InvestmentIndexChange::where('project_id', $a->id)->latest('id')->first()->reasons)->toContain('The project\'s own evidence did not change; other projects in the category changed.')
        ->and(InvestmentIndexRun::where('status', 'superseded')->count())->toBeGreaterThan(0);
});

it('excludes buildings with a verified unsafe finding and classifies risk', function () {
    $official = indexSource('official_record', 'Building Control');
    $p = indexProject('apartment_complex');
    legal($p, $official, 'approved');
    evidence($p, 'occupancy', $official, 20);
    evidence($p, 'structural_safety', $official, 'major_deficiencies');
    evidence($p, 'delivery_status', $official, 'stalled');
    evidence($p, 'location', $official, 'good');
    evidence($p, 'rental_income', $official, 6.0);

    $s = publishedScore($p);
    expect($s->risk_class)->toBe('high')->and($s->risks)->toContain('Major safety deficiencies are on record.', 'Development has stalled.', 'Verified occupancy is 20%.');

    evidence($p, 'structural_safety', $official, 'unsafe', ['observed_on' => now()->subWeek()]);
    expect(publishedScore($p)->status)->toBe('excluded');
});

it('keeps demo data out of public rankings by default', function () {
    expect(config('meezan.investment.demo_mode'))->toBeFalse()
        ->and(InvestmentCalculation::demoMode())->toBeFalse();
});
