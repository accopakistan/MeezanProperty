<?php

use App\Jobs\RecalculateInvestmentIndex;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentLegalRecord;
use App\Models\InvestmentRegistryEntry;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\ProjectAlias;
use App\Models\User;
use App\Services\Agents\CentralAiOrchestrator;
use App\Services\Investment\MarketMetrics;
use App\Services\InvestmentIndex\Collection\Registry;
use App\Services\InvestmentIndex\Collection\SchemeNames;
use App\Services\InvestmentIndex\Coverage;
use App\Services\InvestmentIndex\DataValidator;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Services\InvestmentIndex\IndexEngine;
use App\Services\InvestmentIndex\ProjectDiscovery;
use App\Services\InvestmentIndex\ResearchImport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

/*
| The fixtures here exist only inside each test's rolled-back database. Evidence files
| are the real data files, so these tests also prove the real research imports cleanly.
*/

function rkdImport(): array
{
    Bus::fake([RecalculateInvestmentIndex::class]); // A save schedules a recalculation; the test queue would run one per save.
    // The two real lists these cases need: the whole file takes minutes per test on SQLite.
    $defs = array_values(array_filter(require database_path('data/investment/real_world_sources.php'), fn ($d) => in_array($d['key'], ['mda-private-approved', 'wiki-malls'], true)));
    $file = tempnam(sys_get_temp_dir(), 'rkd');
    file_put_contents($file, '<?php return '.var_export($defs, true).';');
    app(ProjectDiscovery::class)->importFile($file);
    @unlink($file);
    app(ProjectDiscovery::class)->fromRegistry();

    return app(ResearchImport::class)->run();
}

function rkdSource(array $extra = []): MarketSource
{
    $source = new MarketSource(['name' => 'Test authority '.uniqid(), 'type' => 'official_record', 'url' => 'https://authority.example/list', 'publisher' => 'Test authority '.uniqid()]);
    $source->forceFill(['is_active' => true, 'is_demo' => false] + $extra)->save();

    return $source;
}

function rkdProject(array $attributes = []): Project
{
    $project = new Project($attributes + ['name' => 'Test Society '.uniqid(), 'city' => 'Lahore', 'category' => 'residential_society', 'status' => 'unverified', 'description' => 'Test fixture.']);
    $project->forceFill(['is_published' => true, 'is_demo' => false, 'discovery_source_id' => rkdSource()->id, 'discovery_url' => 'https://authority.example/list'])->save();

    return $project;
}

function rkdLegal(Project $project): InvestmentLegalRecord
{
    $source = rkdSource();
    $record = new InvestmentLegalRecord(['project_id' => $project->id, 'authority' => 'LDA', 'record_type' => 'approval', 'status' => 'approved', 'severity' => 'none', 'reference' => 'Test',
        'title' => 'Test', 'issued_on' => now()->subMonth(), 'source_id' => $source->id, 'source_url' => $source->url, 'collected_at' => now(), 'collector' => 'test', 'verification_method' => 'official_registry']);
    $record->forceFill(['verification_status' => 'verified'])->save();

    return $record;
}

it('imports the real research without verifying any of it', function () {
    rkdProject(['name' => 'Campus View Town']);
    $totals = rkdImport();

    expect($totals['projects'])->toBeGreaterThan(5)->and($totals['evidence'])->toBeGreaterThan(5)->and($totals['valuations'])->toBeGreaterThan(0)
        ->and(InvestmentEvidence::where('verification_status', 'verified')->count())->toBe(0)
        ->and(MarketObservation::where('is_demo', false)->where('status', 'verified')->count())->toBe(0)
        ->and(InvestmentEvidence::whereNull('source_id')->count())->toBe(0)
        ->and(InvestmentEvidence::whereNull('source_url')->count())->toBe(0)
        ->and(InvestmentEvidence::whereNull('observed_on')->count())->toBe(0);
});

it('changes nothing on a second import', function () {
    rkdImport();
    $before = [Project::count(), InvestmentEvidence::count(), MarketObservation::count(), InvestmentLegalRecord::count(), Project::where('name', 'Globe Residency')->first()->research_facts];

    rkdImport();

    expect([Project::count(), InvestmentEvidence::count(), MarketObservation::count(), InvestmentLegalRecord::count(), Project::where('name', 'Globe Residency')->first()->research_facts])->toBe($before);
});

it('keeps legal records read by eye from a scan waiting for a person', function () {
    rkdImport();
    $entry = InvestmentRegistryEntry::where('authority', 'MDA Multan')->where('list_type', 'approved')->where('scheme_name', 'like', 'Omega City%')->firstOrFail();
    $record = InvestmentLegalRecord::where('project_id', $entry->project_id)->sole();

    expect($record->verification_status)->toBe('unverified')->and($record->status)->toBe('approved')
        ->and(InvestmentLegalRecord::verified()->where('project_id', $entry->project_id)->count())->toBe(0);

    app(IndexEngine::class)->run('test');
    expect(app(IndexBrowser::class)->score($entry->project)->legal_status)->not->toBe('approved');
});

it('still verifies records copied exactly from an authority\'s own data', function () {
    $project = rkdProject(['name' => 'Exact Copy Town']);
    $source = rkdSource();
    $entry = new InvestmentRegistryEntry;
    $entry->forceFill(['source_id' => $source->id, 'external_key' => 'exact', 'authority' => 'LDA', 'list_type' => 'approved', 'scheme_name' => 'Exact Copy Town', 'normalized_name' => 'exact copy town', 'city' => 'Lahore',
        'raw_status' => 'Approved', 'status' => 'approved', 'source_url' => $source->url, 'content_hash' => 'x', 'raw' => ['name' => 'Exact Copy Town'], 'first_seen_at' => now(), 'last_seen_at' => now(),
        'project_id' => $project->id, 'match_status' => 'matched'])->save();

    expect(app(Registry::class)->record($entry)->verification_status)->toBe('verified');
});

it('does not turn sold apartments, par-value yields or part-building occupancy into evidence', function () {
    rkdImport();

    expect(InvestmentEvidence::where('factor', 'rental_income')->count())->toBe(0)
        ->and(InvestmentEvidence::whereHas('project', fn ($q) => $q->where('name', 'The Centre'))->count())->toBe(0)
        ->and(InvestmentEvidence::whereHas('project', fn ($q) => $q->where('name', 'Globe Residency'))->where('factor', 'occupancy')->count())->toBe(0);
});

it('leaves a reported 0% colonized figure out, and asks staff what the term means', function () {
    rkdImport();
    $source = rkdSource();
    $project = rkdProject(['name' => 'Zero Colonized']);
    $entry = new InvestmentRegistryEntry;
    $entry->forceFill(['source_id' => $source->id, 'external_key' => 'zero', 'authority' => 'LDA', 'list_type' => 'authority_scheme', 'scheme_name' => 'Zero Colonized', 'normalized_name' => 'zero colonized', 'city' => 'Lahore',
        'status' => 'approved', 'source_url' => $source->url, 'content_hash' => 'z', 'raw' => ['colonized' => '0%'], 'first_seen_at' => now(), 'last_seen_at' => now(), 'project_id' => $project->id, 'match_status' => 'matched'])->save();

    app(ResearchImport::class)->run();

    expect(InvestmentEvidence::where('project_id', $project->id)->count())->toBe(0);
});

it('keeps FBR tax values out of every score and price trend', function () {
    $project = rkdProject();
    $source = rkdSource();
    foreach ([['2021-12-01', 1000000], ['2024-10-29', 2000000]] as [$date, $amount]) {
        $o = new MarketObservation(['kind' => 'sale', 'price_basis' => ResearchImport::VALUATION_BASIS, 'country' => 'Pakistan', 'city' => 'Lahore', 'project_id' => $project->id, 'asset_class' => 'residential',
            'property_type' => 'residential_plot', 'size_value' => 1, 'size_unit' => 'marla', 'currency' => 'PKR', 'average_amount' => $amount, 'observed_on' => $date, 'source_id' => $source->id, 'sample_size' => 1, 'methodology' => 'Test fixture']);
        $o->forceFill(['status' => 'verified', 'is_demo' => false, 'fingerprint' => 'fbr'.$date])->saveQuietly();
    }
    rkdLegal($project);

    expect(MarketMetrics::comparableSeries(MarketObservation::where('project_id', $project->id)->get()))->toBeEmpty();

    app(IndexEngine::class)->run('test');
    $score = app(IndexBrowser::class)->score($project);
    expect($score->factors['market_performance']['score'])->toBeNull()
        ->and($score->status)->toBe('insufficient');
});

it('never counts asking prices as history', function () {
    $project = rkdProject();
    $source = rkdSource();
    foreach ([['2024-01-01', 1000000], ['2026-01-01', 3000000]] as [$date, $amount]) {
        $o = new MarketObservation(['kind' => 'sale', 'price_basis' => 'asking', 'country' => 'Pakistan', 'city' => 'Lahore', 'project_id' => $project->id, 'asset_class' => 'residential',
            'property_type' => 'residential_plot', 'size_value' => 1, 'size_unit' => 'marla', 'currency' => 'PKR', 'average_amount' => $amount, 'observed_on' => $date, 'source_id' => $source->id, 'sample_size' => 1, 'methodology' => 'Test fixture']);
        $o->forceFill(['status' => 'verified', 'is_demo' => false, 'fingerprint' => 'ask'.$date])->saveQuietly();
    }
    app(IndexEngine::class)->run('test');

    expect(app(IndexBrowser::class)->score($project)->factors['market_performance']['score'])->toBeNull();

    $this->get(route('rankings.project', $project))->assertOk()
        ->assertSee('Asking prices (advertised, not sales)')->assertSee('Historical performance data unavailable.')
        ->assertDontSee('Verified transaction prices');
});

it('does not rank a project on legal evidence alone', function () {
    $project = rkdProject();
    rkdLegal($project);

    app(IndexEngine::class)->run('test');
    $score = app(IndexBrowser::class)->score($project);

    expect($score->status)->toBe('insufficient')->and($score->category_rank)->toBeNull()->and((float) $score->coverage)->toBeLessThan(0.6)
        ->and($score->confidence_score)->toBeGreaterThan(0);
    $this->get(route('rankings.project', $project))->assertOk()->assertSee('Insufficient Verified Data — Research in Progress')->assertSee('has not scored low');
});

it('keeps every category\'s weights to the approved methodology', function () {
    $expected = [
        'housing-societies' => [25, 20, 15, 10, 10, 10, 5, 5],
        'commercial-plazas' => [20, 20, 15, 15, 10, 10, 5, 5],
        'apartment-buildings' => [20, 15, 15, 15, 10, 10, 5, 5, 5],
        'shopping-malls' => [20, 15, 15, 15, 10, 10, 5, 5, 5],
        'business-towers' => [20, 15, 15, 15, 10, 10, 5, 5, 5],
    ];
    foreach ($expected as $category => $weights) {
        $factors = config("investment_index.categories.$category.factors");
        expect(array_values(array_map(fn ($f) => $f['weight'], $factors)))->toBe($weights)->and(array_sum($weights))->toBe(100);
    }
});

it('keeps demo records out of public pages and the published run', function () {
    $demo = rkdProject(['name' => 'Demo Only Society']);
    $demo->forceFill(['is_demo' => true])->save();
    rkdProject(['name' => 'Real Only Society']);

    app(IndexEngine::class)->run('test');

    $this->get(route('rankings.category', 'housing-societies'))->assertOk()->assertSee('Real Only Society')->assertDontSee('Demo Only Society');
    $this->get(route('rankings.project', $demo))->assertNotFound();
    expect(app(DataValidator::class)->report()['demo_in_published_run']['count'])->toBe(0);
});

it('shows stated facts and unchecked evidence with their sources, marked as unchecked', function () {
    rkdImport();
    app(IndexEngine::class)->run('test');
    $mall = Project::where('name', 'Dolmen Mall, Clifton')->firstOrFail();

    $this->get(route('rankings.project', $mall))->assertOk()
        ->assertSee('Reported, awaiting our check')->assertSee('97.8%')->assertSee('542,847 sq ft')
        ->assertSee('arifhabibdolmenreit.com', false)->assertSee('Unknown / Unverified');
    expect(app(IndexBrowser::class)->score($mall)->factors['occupancy']['score'])->toBeNull();
});

it('labels an unchecked official list entry as unchecked, never as verified', function () {
    rkdImport();
    app(IndexEngine::class)->run('test');
    $project = Project::where('city', 'Multan')->where('name', 'like', 'Omega City%')->firstOrFail();

    $this->get(route('rankings.project', $project))->assertOk()
        ->assertSee('on its list as approved, not yet checked by us')->assertSee('642.88 kanal')->assertDontSee('Verified Approved');
});

it('shows confidence in words and filters by it, by city and by legal status', function () {
    $lahore = rkdProject(['name' => 'Lahore Filter Town']);
    rkdLegal($lahore);
    rkdProject(['name' => 'Karachi Filter Town', 'city' => 'Karachi']);
    app(IndexEngine::class)->run('test');

    $this->get(route('rankings.category', 'housing-societies'))->assertOk()->assertSee('Lahore Filter Town')->assertSee('Karachi Filter Town')->assertSee('Moderate Confidence')->assertSee('Evidence covers');
    $this->get(route('rankings.city-category', ['karachi', 'housing-societies']))->assertOk()->assertSee('Karachi Filter Town')->assertDontSee('Lahore Filter Town');
    $this->get(route('rankings.category', ['category' => 'housing-societies', 'legal' => 'approved']))->assertOk()->assertSee('Lahore Filter Town')->assertDontSee('Karachi Filter Town');
    $this->get(route('rankings.category', ['category' => 'housing-societies', 'confidence' => 'insufficient']))->assertOk()->assertSee('Karachi Filter Town')->assertDontSee('Lahore Filter Town');
});

it('states a coverage gap where nothing is on file', function () {
    app(IndexEngine::class)->run('test');

    $this->get(route('rankings.city-category', ['quetta', 'business-towers']))->assertOk()
        ->assertSee('Coverage gap:')->assertSee('Insufficient Verified Data — Research in Progress')->assertSee('does not mean a project is illegal');
});

it('finds real data problems in the validator', function () {
    $validator = app(DataValidator::class);
    $source = rkdSource();
    $p = new Project(['name' => 'No Source Town', 'city' => 'Lahore', 'category' => 'residential_society', 'status' => 'unverified', 'description' => 'x']);
    $p->forceFill(['is_published' => true, 'is_demo' => false])->save();
    rkdProject(['name' => 'Twin Town']);
    rkdProject(['name' => 'Twin Town']);
    $bad = new MarketObservation(['kind' => 'sale', 'price_basis' => 'transaction', 'country' => 'Pakistan', 'city' => 'Lahore', 'project_id' => $p->id, 'asset_class' => 'residential', 'property_type' => 'house',
        'currency' => 'PKR', 'average_amount' => 1, 'observed_on' => now(), 'source_id' => $source->id, 'sample_size' => 1, 'methodology' => 'Test fixture', 'size_unit' => 'marla', 'size_value' => 1]);
    $bad->forceFill(['status' => 'verified', 'is_demo' => false, 'fingerprint' => 'bad'])->saveQuietly();
    $asking = MarketSource::create(['name' => 'Portal '.uniqid(), 'type' => 'public_asking_prices', 'url' => 'https://portal.example', 'publisher' => 'Portal']);
    $bad->forceFill(['source_id' => $asking->id])->saveQuietly();

    $report = $validator->report();

    expect($report['projects_without_sources']['examples'])->toContain('No Source Town')
        ->and($report['duplicate_projects']['count'])->toBe(1)
        ->and($report['asking_as_transaction']['count'])->toBe(1)
        ->and($report['projects_without_legal_evidence']['severity'])->toBe('info');
    expect(Artisan::call('meezan:investment-validate'))->toBe(1);
});

it('reports the real data as free of validation errors', function () {
    rkdImport();
    app(IndexEngine::class)->run('test');

    expect(collect(app(DataValidator::class)->report())->where('severity', 'error')->sum('count'))->toBe(0)
        ->and(Artisan::call('meezan:investment-validate'))->toBe(0);
});

it('needs a person to verify a whole list, and then counts it', function () {
    rkdImport();
    $admin = User::factory()->admin()->create();
    $source = MarketSource::where('name', 'like', 'MDA — sanctioned%')->firstOrFail();

    $this->actingAs($admin)->post(route('admin.investment-index.review-source', $source->id))->assertSessionHasErrors('checked');
    expect(InvestmentLegalRecord::where('source_id', $source->id)->where('verification_status', 'verified')->count())->toBe(0);

    $this->post(route('admin.investment-index.review-source', $source->id), ['checked' => 1])->assertSessionHas('status');
    expect(InvestmentLegalRecord::where('source_id', $source->id)->where('verification_status', 'unverified')->count())->toBe(0)
        ->and(InvestmentLegalRecord::where('source_id', $source->id)->first()->verified_by)->toBe($admin->id);
});

it('keeps the new admin pages for admins and rejects a demo project alias', function () {
    $this->get(route('admin.investment-index.validation'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.investment-index.validation'))->assertForbidden();
    $this->post(route('admin.investment-index.alias.store'), ['project_id' => 1, 'alias' => 'x'])->assertForbidden();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.investment-index.validation'))->assertOk()->assertSee('Ranking data validation');

    $project = rkdProject(['name' => 'Alias Town']);
    $this->post(route('admin.investment-index.alias.store'), ['project_id' => $project->id, 'alias' => 'Alias Town Old Name'])->assertSessionHas('status');
    expect(ProjectAlias::where('project_id', $project->id)->count())->toBe(1);
    $this->post(route('admin.investment-index.alias.store'), ['project_id' => $project->id, 'alias' => 'Alias Town Old Name'])->assertSessionHas('error');

    $demo = rkdProject(['name' => 'Demo Alias']);
    $demo->forceFill(['is_demo' => true])->save();
    $this->post(route('admin.investment-index.alias.store'), ['project_id' => $demo->id, 'alias' => 'Other'])->assertSessionHasErrors('project_id');
});

it('stops a duplicate project under an alias', function () {
    $project = rkdProject(['name' => 'Original Town']);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.investment-index.alias.store'), ['project_id' => $project->id, 'alias' => 'Old Name Town']);

    $result = app(ProjectDiscovery::class)->ensure(['name' => 'Old Name Town', 'city' => 'Lahore', 'category' => 'residential_society', 'description' => 'x'], null);

    expect($result['created'])->toBeFalse()->and($result['project']->id)->toBe($project->id);
});

it('gives the Investment Analyst the evidence labelled as unchecked, in no score', function () {
    rkdImport();
    app(IndexEngine::class)->run('test');

    $task = app(CentralAiOrchestrator::class)->submit('Show the investment index profile of Dolmen Mall, Clifton', 'investment-analyst', null, 'admin', intent: 'rankings');
    $sections = collect($task->result['sections'])->pluck('heading');

    expect($task->status)->toBe('completed')
        ->and($sections->implode(' '))->toContain('awaiting a staff check')->toContain('What the sources state');
});

it('gives the Global Property Data Manager the validation results', function () {
    $data = app(Coverage::class)->forAgents();

    expect(collect($data['validation'])->pluck('check')->implode(' '))->toContain('Projects with no source of existence');
});

it('never merges names that differ only by a code', function () {
    expect(SchemeNames::similarity('V2 Business Centre', 'V3 Business Centre'))->toBe(0.0)
        ->and(SchemeNames::similarity('Block A1 Town', 'Block A2 Town'))->toBe(0.0)
        ->and(SchemeNames::similarity('V2 Business Centre', 'V2 Business Centre'))->toBe(1.0);
});

it('marks every hand-read official list for staff review', function () {
    $lists = collect(require database_path('data/investment/real_world_sources.php'))->where('list_type', '!=', 'reference');

    expect($lists)->not->toBeEmpty()
        ->and($lists->reject(fn ($d) => $d['review'] ?? false)->pluck('key')->all())->toBe([]);
});

it('moves only automatically verified hand-read records to review', function () {
    $source = rkdSource(['name' => 'QDA — approved private housing schemes']);
    $other = rkdSource();
    $auto = rkdLegal(rkdProject());
    $auto->forceFill(['source_id' => $source->id])->save();
    $human = rkdLegal(rkdProject());
    $human->forceFill(['source_id' => $source->id, 'verification_method' => 'staff_review', 'verified_by' => User::factory()->admin()->create()->id, 'verified_at' => now()])->save();
    $direct = rkdLegal(rkdProject());
    $direct->forceFill(['source_id' => $other->id])->save();

    (require database_path('migrations/2026_11_05_000058_move_hand_read_legal_records_to_review.php'))->up();

    expect($auto->fresh())->verification_status->toBe('unverified')->verification_method->toBe('awaiting_staff_review')->verified_at->toBeNull()
        ->and($human->fresh()->verification_status)->toBe('verified')
        ->and($direct->fresh()->verification_status)->toBe('verified')
        ->and(InvestmentLegalRecord::count())->toBe(3);
});

it('shows a moved project as unverified, and verified again once a person checks the list', function () {
    $source = rkdSource(['name' => 'QDA — approved private housing schemes']);
    $project = rkdProject(['name' => 'Moved Town', 'city' => 'Quetta']);
    $record = rkdLegal($project);
    $record->forceFill(['source_id' => $source->id])->save();
    (require database_path('migrations/2026_11_05_000058_move_hand_read_legal_records_to_review.php'))->up();
    app(IndexEngine::class)->run('test');

    expect(app(IndexBrowser::class)->score($project)->legal_status)->toBe('not_verified');
    $this->get(route('rankings.project', $project))->assertOk()->assertSee('on its list as approved, not yet checked by us')->assertSee('Unknown / Unverified');

    $this->actingAs(User::factory()->admin()->create())->post(route('admin.investment-index.review-source', $source->id), ['checked' => 1])->assertSessionHas('status');
    $fresh = app(IndexEngine::class)->run('test2');
    expect(InvestmentLegalRecord::verified()->where('project_id', $project->id)->count())->toBe(1)
        ->and($fresh->scores()->where('project_id', $project->id)->first()?->legal_status ?? InvestmentIndexRun::latestPublished()->scores()->where('project_id', $project->id)->first()->legal_status)->toBe('approved');
});
