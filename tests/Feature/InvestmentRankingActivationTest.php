<?php

use App\Jobs\RecalculateInvestmentIndex;
use App\Models\AppSetting;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
use App\Models\InvestmentLegalRecord;
use App\Models\InvestmentRegistryEntry;
use App\Models\MarketObservation;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Services\InvestmentIndex\IndexEngine;
use App\Services\InvestmentIndex\ProjectDiscovery;
use App\Services\InvestmentIndex\ResearchImport;
use App\Support\Cities;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

/*
| Fixtures live only inside each test's rolled-back database. Nothing here reaches the
| web, and the import tests use the real research files.
*/

beforeEach(fn () => Http::fake());

function actImport(): array
{
    Bus::fake([RecalculateInvestmentIndex::class]); // A save schedules a recalculation; the test queue would run one per save.
    // The two lists the research refers to: the whole file takes minutes per test on SQLite.
    $defs = array_values(array_filter(require database_path('data/investment/real_world_sources.php'), fn ($d) => in_array($d['key'], ['mda-private-approved', 'wiki-malls'], true)));
    $file = tempnam(sys_get_temp_dir(), 'act');
    file_put_contents($file, '<?php return '.var_export($defs, true).';');
    app(ProjectDiscovery::class)->importFile($file);
    @unlink($file);
    app(ProjectDiscovery::class)->fromRegistry();

    return app(ResearchImport::class)->run();
}

function actSource(string $type = 'official_record', int $level = 1): MarketSource
{
    $source = new MarketSource(['name' => 'Activation source '.uniqid(), 'type' => $type, 'url' => 'https://example.gov.pk/list', 'publisher' => 'Authority', 'reliability' => 'high', 'coverage' => 'Lahore', 'last_checked_on' => now()]);
    $source->forceFill(['is_active' => true, 'is_demo' => false, 'authority_level' => $level])->save();

    return $source;
}

function actProject(array $attributes = []): Project
{
    $project = new Project($attributes + ['name' => 'Activation '.uniqid(), 'city' => 'Lahore', 'category' => 'residential_society', 'status' => 'unverified', 'description' => 'A test project.']);
    $project->forceFill(['is_published' => true, 'is_demo' => $attributes['is_demo'] ?? false, 'discovery_source_id' => actSource()->id, 'discovery_url' => 'https://example.gov.pk/list'])->save();

    return $project;
}

function actLegal(Project $project, string $verification = 'unverified', string $status = 'approved', ?MarketSource $source = null): InvestmentLegalRecord
{
    $source ??= actSource();
    $record = new InvestmentLegalRecord(['project_id' => $project->id, 'authority' => 'LDA', 'record_type' => $status === 'cancelled' ? 'cancellation' : 'approval', 'status' => $status, 'severity' => $status === 'cancelled' ? 'critical' : 'none', 'reference' => 'Test',
        'title' => 'Test', 'issued_on' => now()->subMonth(), 'source_id' => $source->id, 'source_url' => $source->url, 'collected_at' => now(), 'collector' => 'test', 'verification_method' => 'official_registry']);
    $record->forceFill(['verification_status' => $verification])->save();

    return $record;
}

function actEvidence(Project $project, string $factor = 'occupancy', string $verification = 'unverified', float $percent = 80): InvestmentEvidence
{
    $source = actSource();
    $evidence = new InvestmentEvidence(['project_id' => $project->id, 'factor' => $factor, 'value_numeric' => $percent, 'unit' => 'percent', 'observed_on' => now()->subMonth(), 'source_id' => $source->id, 'source_url' => $source->url, 'collected_at' => now(), 'excerpt' => 'Read from the page.', 'collector' => 'test', 'verification_method' => 'awaiting_staff_review']);
    $evidence->forceFill(['verification_status' => $verification])->save();

    return $evidence;
}

function actRun(): InvestmentIndexRun
{
    return app(IndexEngine::class)->run('test');
}

function actRow(Project $project): InvestmentIndexScore
{
    return InvestmentIndexScore::where('run_id', app(IndexBrowser::class)->run()->id)->where('project_id', $project->id)->firstOrFail();
}

it('opens all five leaderboards and every city filter, listing researched projects with no score', function () {
    $projects = collect(array_keys(IndexEngine::categoryMap()))->map(fn ($c) => actProject(['category' => $c, 'name' => 'Listed '.$c]));
    actRun();

    foreach (IndexBrowser::categories() as $key => $category) {
        $this->get(route('rankings.category', $key))->assertOk()->assertSee('Listed')->assertSee('Assessed, not ranked');
        foreach (Cities::names() as $city) {
            $this->get(route('rankings.city-category', [Cities::slug($city), $key]))->assertOk();
        }
    }
    foreach (Cities::names() as $city) {
        $this->get(route('rankings.city', Cities::slug($city)))->assertOk()->assertSee('Projects researched');
    }
    $this->get(route('rankings.index'))->assertOk()->assertSee('Most documented so far, not a ranking')->assertSee('Listed residential_society');
    expect($projects->pluck('category')->unique())->toHaveCount(count(IndexEngine::categoryMap()));
});

it('never gives a provisional figure the status of a verified rank', function () {
    $project = actProject(['name' => 'Provisional Scheme']);
    actLegal($project);
    actEvidence($project);
    actRun();

    $row = actRow($project);
    expect($row->status)->toBe('insufficient')->and($row->score)->toBeNull()->and($row->category_rank)->toBeNull()
        ->and($row->provisional_rank)->toBe(1)->and((float) $row->provisional_score)->toBeGreaterThan(0)
        ->and((float) $row->provisional_coverage)->toBeLessThan(0.6)->and($row->provisional_unchecked)->toBe(2);
});

it('is deterministic: the same evidence gives the same provisional figures', function () {
    $project = actProject();
    actLegal($project);
    actEvidence($project);
    actRun();
    $first = actRow($project);
    $run = app(IndexBrowser::class)->run()->id;

    actRun();

    expect(app(IndexBrowser::class)->run()->id)->toBe($run)
        ->and(actRow($project)->provisional_score)->toBe($first->provisional_score);
});

it('gives no provisional figure without two scored factors, enough weight and a usable legal status', function () {
    $legalOnly = actProject();
    actLegal($legalOnly);
    $noLegal = actProject();
    actEvidence($noLegal);
    $empty = actProject();
    $adverse = actProject();
    actLegal($adverse, 'verified', 'cancelled');
    actEvidence($adverse);
    actRun();

    foreach ([$legalOnly, $noLegal, $empty, $adverse] as $project) {
        expect(actRow($project)->provisional_rank)->toBeNull()->and(actRow($project)->provisional_score)->toBeNull();
    }
    expect(actRow($adverse)->status)->toBe('excluded');
});

it('orders provisional positions by score, then evidence, then id', function () {
    $low = actProject();
    actLegal($low);
    actEvidence($low, percent: 40);
    $high = actProject();
    actLegal($high);
    actEvidence($high, percent: 95);
    $tieA = actProject();
    actLegal($tieA);
    actEvidence($tieA, percent: 70);
    $tieB = actProject();
    actLegal($tieB);
    actEvidence($tieB, percent: 70);
    actRun();

    expect(actRow($high)->provisional_rank)->toBe(1)->and(actRow($tieA)->provisional_rank)->toBe(2)
        ->and(actRow($tieB)->provisional_rank)->toBe(3)->and(actRow($low)->provisional_rank)->toBe(4);
});

it('leaves verified scoring unchanged: rejected records count for nothing, and unchecked ones reach no verified score', function () {
    $project = actProject();
    actLegal($project);
    actEvidence($project);
    $rejected = actProject();
    actLegal($rejected, 'rejected');
    actEvidence($rejected, 'occupancy', 'rejected');
    actRun();

    expect((float) actRow($project)->coverage)->toBe(0.0)->and(actRow($project)->legal_status)->toBe('not_verified')
        ->and(actRow($rejected)->provisional_rank)->toBeNull()->and(actRow($rejected)->research_depth)->toBe(0);
});

it('keeps provisional positions off public pages until the owner turns them on', function () {
    $project = actProject(['name' => 'Provisional Scheme']);
    actLegal($project);
    actEvidence($project);
    actRun();

    expect(IndexBrowser::provisionalPublic())->toBeFalse();
    foreach ([route('rankings.category', 'housing-societies'), route('rankings.index'), route('rankings.project', $project)] as $url) {
        $this->get($url)->assertOk()->assertDontSee('Provisional research')->assertDontSee('P1');
    }

    AppSetting::put('investment_index.provisional_public', true);

    $this->get(route('rankings.category', 'housing-societies'))->assertOk()->assertSee('Provisional research ranking')->assertSee('Not fully verified, and not an investment recommendation')->assertSee('P1');
    $this->get(route('rankings.project', $project))->assertOk()->assertSee('Provisional research position P1')->assertSee('not yet checked');
    $this->get(route('rankings.methodology'))->assertOk()->assertSee('Provisional research position');
});

it('says a project is on an authority list but unchecked, and never calls an unknown status illegal', function () {
    $waiting = actProject(['name' => 'Waiting Tower', 'category' => 'apartment_complex']);
    actLegal($waiting);
    $unknown = actProject(['name' => 'Unknown Tower', 'category' => 'apartment_complex']);
    actRun();

    $this->get(route('rankings.category', 'apartment-buildings'))->assertOk()
        ->assertSee("On LDA's list, not yet checked by us")->assertSee('Unknown / Unverified')->assertDontSee('Illegal');
    expect(actRow($waiting)->legal_status)->toBe('not_verified')->and(actRow($unknown)->legal_status)->toBe('not_verified');
});

it('leaves demo projects out of the research state and every public list', function () {
    $demo = actProject(['name' => 'Demo Scheme', 'is_demo' => true]);
    actLegal($demo);
    actEvidence($demo);
    actRun();

    expect(InvestmentIndexScore::where('project_id', $demo->id)->exists())->toBeFalse();
    $this->get(route('rankings.index'))->assertOk()->assertDontSee('Demo Scheme');
    $this->get(route('rankings.category', 'housing-societies'))->assertOk()->assertDontSee('Demo Scheme');
});

it('imports market research as asking figures that reach no score, and stays the same on a second run', function () {
    actImport();
    actRun();

    $dha = Project::where('name', 'DHA Karachi')->where('city', 'Karachi')->firstOrFail();
    $records = MarketObservation::where('project_id', $dha->id)->get();

    expect($records)->not->toBeEmpty()
        ->and($records->pluck('price_basis')->unique()->all())->toBe(['asking'])
        ->and($records->pluck('status')->unique()->all())->toBe(['submitted'])
        ->and($dha->is_demo)->toBeFalse()->and(actRow($dha)->legal_status)->toBe('not_verified')
        ->and((float) actRow($dha)->coverage)->toBe(0.0);

    // Facts carry their source and say what they are.
    $fact = collect($dha->research_facts)->firstWhere('label', 'Average advertised house price, Sep 2026');
    expect($fact['value'])->toContain('Asking-based')->and($fact['url'])->toContain('zameen.com/index/')->and($fact['source'])->toContain('Zameen');

    $projects = Project::count();
    $observations = MarketObservation::count();
    actImport();
    expect(Project::count())->toBe($projects)->and(MarketObservation::count())->toBe($observations);
});

it('keeps the facts already on a project when a portal listing matches it', function () {
    $existing = actProject(['name' => 'Zameen Jade', 'category' => 'apartment_complex']);
    $earlier = ['label' => 'Location on the official list', 'value' => 'Block 1', 'source' => 'An authority', 'url' => 'https://example.gov.pk/list', 'date' => '2026-11-01', 'basis' => 'Official'];
    $existing->forceFill(['research_facts' => [$earlier]])->save();

    actImport();
    $facts = collect($existing->fresh()->research_facts);

    expect($facts->firstWhere('label', 'Location on the official list')['value'])->toBe('Block 1')
        ->and($facts->firstWhere('label', 'Location advertised')['value'])->toBe('Main Canal Bank Road, Lahore');

    actImport();
    expect($existing->fresh()->research_facts)->toHaveCount($facts->count());
});

it('shows asking figures as facts on the page, never as a price trend', function () {
    actImport();
    actRun();
    $dha = Project::where('name', 'DHA Karachi')->firstOrFail();

    $this->get(route('rankings.project', $dha))->assertOk()->assertSee('Average advertised house price, Sep 2026')->assertSee('Asking-based, not sales');
});

it('keeps SBCA building records waiting for a person', function () {
    $defs = collect(require database_path('data/investment/sbca_building_nocs.php'));

    expect($defs)->toHaveCount(5)
        ->and($defs->pluck('review')->unique()->all())->toBe([true])
        ->and($defs->pluck('category_basis')->unique()->all())->toBe(['inferred'])
        ->and($defs->pluck('category')->sort()->values()->all())->toBe(['apartment_complex', 'business_tower', 'commercial_plaza', 'residential_society', 'shopping_mall']);

    $lines = $defs->flatMap(fn ($d) => preg_split('/\R/', trim($d['lines'])));
    $names = $lines->map(fn ($l) => explode('|', $l)[0]);
    expect($names->duplicates()->all())->toBe([])
        ->and($names->contains('NAYA NAZIMABAD APARTMENT-3'))->toBeFalse()
        ->and($lines->every(fn ($l) => count(explode('|', $l)) === 5))->toBeTrue()
        ->and($lines->every(fn ($l) => str_starts_with(explode('|', $l)[4], 'SBCA/')))->toBeTrue();
});

it('fills a blank developer from an official list and never overwrites one', function () {
    $blank = actProject(['name' => 'Blank Builder Tower', 'city' => 'Karachi', 'category' => 'apartment_complex']);
    $named = actProject(['name' => 'Named Builder Tower', 'city' => 'Karachi', 'category' => 'apartment_complex', 'developer' => 'Already Recorded']);
    $source = actSource();
    foreach ([$blank, $named] as $project) {
        $entry = new InvestmentRegistryEntry;
        $entry->forceFill(['source_id' => $source->id, 'external_key' => 'key-'.$project->id, 'authority' => 'SBCA', 'list_type' => 'approved', 'scheme_name' => $project->name, 'normalized_name' => mb_strtolower($project->name), 'city' => 'Karachi',
            'raw_status' => 'NOC issued', 'status' => 'approved', 'source_url' => $source->url, 'content_hash' => 'x'.$project->id, 'raw' => ['_developer' => 'From The List', '_review' => true], 'first_seen_at' => now(), 'last_seen_at' => now(),
            'project_id' => $project->id, 'match_status' => 'matched'])->save();
    }

    (new ReflectionMethod(ResearchImport::class, 'officialFacts'))->invoke(app(ResearchImport::class));

    expect($blank->fresh()->developer)->toBe('From The List')->and($named->fresh()->developer)->toBe('Already Recorded');
});

it('lets staff work through the research queue, and only a person changes anything', function () {
    $admin = User::factory()->admin()->create();
    $project = actProject(['name' => 'Queue Scheme', 'city' => 'Karachi', 'category' => 'apartment_complex']);
    $record = actLegal($project);
    $bare = actProject(['name' => 'Bare Scheme', 'city' => 'Lahore']);
    actRun();

    $this->get(route('admin.investment-index.research'))->assertRedirect();
    $this->actingAs(User::factory()->create())->get(route('admin.investment-index.research'))->assertForbidden();

    $this->actingAs($admin);
    $this->get(route('admin.investment-index.research'))->assertOk()->assertSee('Queue Scheme')->assertSee('Bare Scheme')
        ->assertSee('Legal record waiting for a person')->assertSee('Latest calculations')->assertSee('Latest collection jobs')->assertSee('Hidden from visitors');
    $this->get(route('admin.investment-index.research', ['gap' => 'awaiting']))->assertOk()->assertSee('Queue Scheme')->assertDontSee('Bare Scheme');
    $this->get(route('admin.investment-index.research', ['gap' => 'legal']))->assertOk()->assertSee('Bare Scheme')->assertDontSee('Queue Scheme');
    $this->get(route('admin.investment-index.research', ['city' => 'Karachi', 'category' => 'apartment_complex']))->assertOk()->assertSee('Queue Scheme')->assertDontSee('Bare Scheme');
    $this->get(route('admin.investment-index.verification', ['project' => $project->id]))->assertOk()->assertSee('Showing records for')->assertSee('Queue Scheme');

    $this->post(route('admin.investment-index.research.request', $bare->id), ['note' => 'Find the LDA record.'])->assertRedirect();
    expect(StaffTask::where('related_id', $bare->id)->where('related_type', 'project')->value('description'))->toBe('Find the LDA record.')
        ->and($record->fresh()->verification_status)->toBe('unverified');

    $this->get(route('rankings.project', $bare))->assertOk()->assertDontSee('Find the LDA record.');
});

it('needs the owner to confirm before showing provisional positions, and records who did it', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->patch(route('admin.investment-index.research.provisional'), ['on' => 1])->assertSessionHasErrors('confirmed');
    expect(IndexBrowser::provisionalPublic())->toBeFalse();

    $this->patch(route('admin.investment-index.research.provisional'), ['on' => 1, 'confirmed' => 1])->assertRedirect();
    expect(IndexBrowser::provisionalPublic())->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'investment_index.provisional_switched']);

    $this->patch(route('admin.investment-index.research.provisional'), ['on' => 0])->assertRedirect();
    expect(IndexBrowser::provisionalPublic())->toBeFalse();

    $this->actingAs(User::factory()->create());
    $this->patch(route('admin.investment-index.research.provisional'), ['on' => 1, 'confirmed' => 1])->assertForbidden();
});

it('explains the display states on the public methodology page', function () {
    actRun();

    $this->get(route('rankings.methodology'))->assertOk()->assertSee('How a project is shown')->assertSee('Research in progress')
        ->assertSee('A missing value is never treated as a good one')->assertDontSee('Provisional research position');
});

it('does not fetch anything from the web to show a public page', function () {
    actProject(['name' => 'Offline Scheme']);
    actRun();

    $this->get(route('rankings.index'))->assertOk();
    $this->get(route('rankings.category', 'housing-societies'))->assertOk();

    Http::assertNothingSent();
});
