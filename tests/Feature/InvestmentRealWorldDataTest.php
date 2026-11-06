<?php

use App\Models\AiAgent;
use App\Models\AiTask;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentException;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentLegalRecord;
use App\Models\InvestmentRegistryEntry;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\ProjectAlias;
use App\Models\User;
use App\Services\Agents\CentralAiOrchestrator;
use App\Services\InvestmentIndex\Collection\CollectionService;
use App\Services\InvestmentIndex\Collection\OfficialStatus;
use App\Services\InvestmentIndex\Collection\SchemeNames;
use App\Services\InvestmentIndex\Coverage;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Services\InvestmentIndex\IndexEngine;
use App\Services\InvestmentIndex\ProjectDiscovery;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/*
| The real-world project pipeline: official lists and hand-read sources become
| traceable project records. Every list here is a fixture; nothing leaves the test.
*/

function rwSource(string $collector): MarketSource
{
    $preset = config("investment_index.collectors.$collector");
    $s = MarketSource::create(['name' => $preset['label'], 'type' => 'official_record', 'url' => $preset['url'], 'publisher' => $preset['authority'], 'reliability' => 'high', 'coverage' => $preset['city']]);
    $s->forceFill(['collector' => $collector, 'is_active' => true, 'access_basis' => 'public_record', 'authorized_at' => now()])->save();

    return $s;
}

function rwCard(string $name, string $lop, string $noc): string
{
    return '<div class="card mb-3 society-card"><div class="card-body"><div><div><h5><a href="https://www.cda.gov.pk/housing-schemes/'.Str::slug($name).'">'.$name.'</a></h5>'
        .'<p class="mb-0"><strong>Zone:</strong> Zone 5 | <strong>Road:</strong> GT Road</p></div><div><span class="badge bg-success"> LOP: '.$lop.' </span><span class="badge bg-warning"> NOC: '.$noc.' </span></div></div></div></div>';
}

function rwFile(array $sources): string
{
    $path = tempnam(sys_get_temp_dir(), 'rw').'.php';
    file_put_contents($path, '<?php return '.var_export($sources, true).';');

    return $path;
}

function rwOfficial(array $over = []): array
{
    return $over + ['key' => 'x', 'name' => 'Test authority approved list', 'publisher' => 'Test Authority', 'authority' => 'TDA', 'city' => 'Quetta', 'type' => 'official_record', 'level' => 1, 'url' => 'https://example.gov.pk/list',
        'read_on' => '2026-11-04', 'method' => 'official_pdf', 'list_type' => 'approved', 'category' => 'residential_society', 'category_basis' => 'official', 'raw_status' => 'Approved', 'access' => 'Read once.', 'lines' => "Garden Town|Spinny Road|15-08-2005\nThe Garden-Town|Spinny Road|15-08-2005"];
}

/*
| Names and project identity
*/

it('never treats different sections of a scheme as the same project', function () {
    foreach ([['Orangi Town Residency Block-B', 'Orangi Town Residency Block-C'], ['Govt Officer Society Sector A & B', 'Govt Officer Society Sector C & D'], ['Gulistan Colony-1', 'Gulistan Colony-2'],
        ['Karam LSD (1st Part)', 'Karam LSD (2nd Part)'], ['Gohar Villas-I', 'Gohar Villas-II'], ['Nespak Ph-II Site-I', 'Nespak Ph-II Site-II']] as [$a, $b]) {
        expect(SchemeNames::similarity($a, $b))->toBe(0.0, "$a / $b");
    }
    // A section on one side only is never an automatic match; a spelling difference is.
    expect(SchemeNames::similarity('Sitara Park City', 'Sitara Park City Extension'))->toBeLessThan((float) config('investment_index.collection.match_auto'))
        ->and(SchemeNames::similarity('Al-Hamra Town', 'Al Hamra Town'))->toBe(1.0)
        ->and(SchemeNames::similarity('Green Palms Housing Scheme', 'Green Palms'))->toBe(1.0);
});

it('writes capitalised official names readably, without damaging acronyms or numerals', function () {
    expect(ProjectDiscovery::displayName('MALIR TOWN RESIDENCY PHASE-III'))->toBe('Malir Town Residency Phase-III')
        ->and(ProjectDiscovery::displayName('PS CITY PHASE-II'))->toBe('PS City Phase-II')
        ->and(ProjectDiscovery::displayName('PHAF'))->toBe('PHAF')
        ->and(ProjectDiscovery::displayName('Al-Hamra Town'))->toBe('Al-Hamra Town');
});

it('keeps the same project once when sources spell it differently, recording the alias, and stays idempotent', function () {
    $discovery = app(ProjectDiscovery::class);
    $path = rwFile([rwOfficial()]);

    $discovery->importFile($path);
    $discovery->fromRegistry();
    $counts = [Project::where('is_demo', false)->count(), InvestmentRegistryEntry::count(), InvestmentLegalRecord::count(), MarketSource::where('name', 'Test authority approved list')->count()];
    $discovery->importFile($path);
    $discovery->fromRegistry();

    // Two official lines, one project; each line keeps its own traceable record.
    expect($counts)->toBe([1, 2, 2, 1])
        ->and([Project::where('is_demo', false)->count(), InvestmentRegistryEntry::count(), InvestmentLegalRecord::count(), MarketSource::where('name', 'Test authority approved list')->count()])->toBe($counts);
    expect(ProjectAlias::where('alias', 'The Garden-Town')->exists())->toBeTrue();

    $p = Project::where('is_demo', false)->sole();
    expect($p->status)->toBe('unverified')->and($p->discovery_url)->toBe('https://example.gov.pk/list#garden-town-spinny-road')->and($p->description)->toContain('has not inspected')
        ->and($p->latitude)->toBeNull();
    $record = InvestmentLegalRecord::where('project_id', $p->id)->first();
    expect($record->verification_status)->toBe('verified')->and($record->source_url)->toContain('https://example.gov.pk/list')->and($record->issued_on->toDateString())->toBe('2005-08-15');
});

it('never creates a project from an illegal list, and names a matching project only through the official record', function () {
    $discovery = app(ProjectDiscovery::class);
    $discovery->importFile(rwFile([rwOfficial(['lines' => 'Peshawar Garden|GT Road', 'list_type' => 'illegal', 'raw_status' => 'UN-APPROVED'])]));
    $discovery->fromRegistry();

    expect(Project::where('is_demo', false)->count())->toBe(0)->and(InvestmentRegistryEntry::where('list_type', 'illegal')->count())->toBe(1);
    app(IndexEngine::class)->run('test');
    $this->get(route('rankings.category', 'housing-societies'))->assertDontSee('Peshawar Garden');
});

it('keeps secondary references unverified: a name and a source, no legal status, no score', function () {
    $discovery = app(ProjectDiscovery::class);
    $discovery->importFile(rwFile([['key' => 'r', 'name' => 'Test reference', 'publisher' => 'Wikipedia', 'type' => 'secondary_reference', 'level' => 7, 'url' => 'https://en.wikipedia.org/wiki/List', 'read_on' => '2026-11-04', 'method' => 'public_reference',
        'list_type' => 'reference', 'category' => 'shopping_mall', 'category_basis' => 'source', 'access' => 'Public reference.', 'cities' => ['Lahore' => ['Fixture Mall|https://fixture-mall.example']]]]));
    app(IndexEngine::class)->run('test');

    $p = Project::where('name', 'Fixture Mall')->sole();
    $score = InvestmentIndexRun::latestPublished()->scores()->where('project_id', $p->id)->sole();
    expect($score->status)->toBe('insufficient')->and($score->legal_status)->toBe('not_verified')->and(InvestmentLegalRecord::where('project_id', $p->id)->count())->toBe(0);
    $this->get(route('rankings.project', $p))->assertOk()->assertSee('Unknown / Unverified')->assertSee('Secondary public reference')->assertSee('developer-reported')->assertSee('Unverified')->assertDontSee('Verified Approved');
});

it('stores no personal identifiers from the source lists', function () {
    $text = file_get_contents(database_path('data/investment/real_world_sources.php'));

    expect($text)->not->toMatch('/\b\d{5}-\d{7}-\d\b/')->not->toMatch('/\b03\d{2}-?\d{7}\b/')->not->toContain('Muazam Malik')->not->toContain('Naseeb Ullah');
});

/*
| Official lists
*/

it('reads CDA\'s two approval steps conservatively', function () {
    expect(OfficialStatus::for('approved', 'LOP: Approved / NOC: Issued', 'cda'))->toBe('approved')
        ->and(OfficialStatus::for('approved', 'LOP: Approved / NOC: Not Issued', 'cda'))->toBe('partially_approved')
        ->and(OfficialStatus::for('approved', 'LOP: Provisional Approval / NOC: Not Issued', 'cda'))->toBe('pending')
        ->and(OfficialStatus::for('approved', 'LOP: Approved / NOC: Cancelled', 'cda'))->toBe('cancelled')
        ->and(OfficialStatus::for('approved', 'LOP: Cancelled / NOC: Not Issued', 'cda'))->toBe('cancelled')
        ->and(OfficialStatus::for('approved', 'something else', 'cda'))->toBeNull();
});

it('collects CDA cards into projects, excludes cancelled schemes and checks robots first', function () {
    Http::fake(['www.cda.gov.pk/robots.txt' => Http::response("User-agent: *\nDisallow:"), 'www.cda.gov.pk/housing-schemes*' => Http::response(rwCard('Alpha Enclave', 'Approved', 'Issued').rwCard('Beta Town', 'Approved', 'Not Issued').rwCard('Gamma City', 'Approved', 'Cancelled'))]);
    $source = rwSource('cda-housing');

    $run = app(CollectionService::class)->collect($source);
    expect($run->status)->toBe('completed')->and($run->entries_found)->toBe(3);
    app(ProjectDiscovery::class)->fromRegistry();
    app(IndexEngine::class)->run('test');

    $run = InvestmentIndexRun::latestPublished();
    $legal = fn (string $n) => $run->scores()->where('project_id', Project::where('name', $n)->value('id'))->sole();
    expect($legal('Alpha Enclave')->legal_status)->toBe('approved')->and($legal('Beta Town')->legal_status)->toBe('partially_approved')
        ->and($legal('Gamma City')->status)->toBe('excluded')->and($legal('Alpha Enclave')->status)->toBe('insufficient');
    expect(InvestmentRegistryEntry::where('scheme_name', 'Alpha Enclave')->value('location'))->toBe('Zone: Zone 5, Road: GT Road');
    $this->get(route('rankings.project', Project::where('name', 'Gamma City')->sole()))->assertSee('Cancelled')->assertSee('Excluded');
});

it('does not read a source whose robots.txt forbids it or fails', function () {
    foreach ([[Http::response("User-agent: *\nDisallow: /"), 200], [Http::response('error', 500), 500]] as [$robots]) {
        Http::fake(['www.cda.gov.pk/robots.txt' => $robots, 'www.cda.gov.pk/*' => Http::response(rwCard('Delta Town', 'Approved', 'Issued'))]);
        $source = MarketSource::where('collector', 'cda-housing')->first() ?? rwSource('cda-housing');
        expect(app(CollectionService::class)->collect($source)->status)->toBe('blocked');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'housing-schemes'));
        Http::swap(new Factory);
    }
    expect(InvestmentRegistryEntry::count())->toBe(0);
});

/*
| Public pages
*/

beforeEach(function () {
    $this->seedPublic = function () {
        $discovery = app(ProjectDiscovery::class);
        $discovery->importFile(rwFile([rwOfficial(['city' => 'Lahore', 'lines' => "Model Enclave|Canal Road|15-08-2005\nRiver Gardens|Ring Road|01-02-2020"])]));
        $discovery->fromRegistry();
        app(IndexEngine::class)->run('test');
    };
});

it('shows genuine counts on city cards, and city category pages at their own address', function () {
    ($this->seedPublic)();
    $demo = new Project(['name' => 'Fixture Fictional Scheme', 'category' => 'residential_society', 'city' => 'Lahore', 'status' => 'completed', 'description' => 'A demo project used by the tests only.']);
    $demo->forceFill(['is_published' => true, 'is_demo' => true])->save();
    app(IndexEngine::class)->run('test');

    $lahore = collect(app(IndexBrowser::class)->cityCards())->firstWhere('name', 'Lahore');
    expect($lahore['projects'])->toBe(2)->and($lahore['insufficient'])->toBe(2);
    $this->get(route('rankings.index'))->assertOk()->assertSee('Meezan Investment Index — Research, Verification &amp; Investment Intelligence', false)->assertSee('Choose a city')->assertSee('Insufficient Verified Data');

    $url = route('rankings.city-category', ['lahore', 'housing-societies']);
    expect($url)->toEndWith('/investment-rankings/lahore/housing-societies');
    // Nothing ranked yet: useful to visitors, but kept out of search engines like every page without results.
    $this->get($url)->assertOk()->assertSee('Model Enclave')->assertSee('Verified Approved')->assertSee('noindex', false)->assertDontSee('Fixture Fictional Scheme');
    $this->get(route('rankings.city-category', ['karachi', 'housing-societies']))->assertOk()->assertDontSee('Model Enclave');
    $this->get('/investment-rankings/london/housing-societies')->assertNotFound();
    $this->get('/investment-rankings?city=Lahore&category=shopping-malls&q=abc')->assertRedirect(route('rankings.city-category', ['lahore', 'shopping-malls', 'q' => 'abc']));
});

it('filters a category page by search, legal status and development status', function () {
    ($this->seedPublic)();
    $base = route('rankings.category', 'housing-societies');

    $this->get($base.'?q=River')->assertOk()->assertSee('River Gardens')->assertDontSee('Model Enclave');
    $this->get($base.'?legal=not_verified')->assertOk()->assertDontSee('River Gardens')->assertSee('Nothing here matches');
    $this->get($base.'?legal=approved&city=Lahore')->assertOk()->assertSee('River Gardens');
    $this->get($base.'?development=completed')->assertOk()->assertDontSee('River Gardens');
    $this->get($base.'?legal=<script>')->assertOk()->assertSee('River Gardens');
});

it('compares only developments of one category, with missing data marked and no score for the unranked', function () {
    ($this->seedPublic)();
    $mall = new Project(['name' => 'Other Category Mall', 'category' => 'shopping_mall', 'city' => 'Lahore', 'status' => 'unverified', 'description' => 'A fixture mall used by the tests only.']);
    $mall->forceFill(['is_published' => true, 'is_demo' => false])->save();
    $slugs = Project::where('category', 'residential_society')->where('is_demo', false)->pluck('slug')->push($mall->slug)->all();

    $this->get(route('rankings.category-compare', ['housing-societies', 'projects' => $slugs]))->assertOk()->assertSee('Model Enclave')->assertSee('River Gardens')->assertDontSee('Other Category Mall')
        ->assertSee('Not eligible')->assertSee('Insufficient Verified Data')->assertSee('noindex', false);
    $this->get(route('rankings.category-compare', 'housing-societies'))->assertOk()->assertSee('Tick up to four');
});

it('shows data status, origin, missing information and the source on a project profile', function () {
    ($this->seedPublic)();
    $p = Project::where('name', 'Model Enclave')->sole();

    $this->get(route('rankings.project', $p))->assertOk()->assertSee('Partially Verified')->assertSee('Test authority approved list')->assertSee('Missing information')->assertSee('Occupancy')
        ->assertSee('Verified Approved')->assertSee('https://example.gov.pk/list', false)->assertSee('Status not verified', false);
});

it('marks a project with conflicting official records as disputed', function () {
    ($this->seedPublic)();
    $p = Project::where('name', 'Model Enclave')->sole();
    InvestmentException::raise('legal_conflict', 'Official records disagree', 'Both lists.', ['project_id' => $p->id], ['key' => 'c']);
    expect(app(IndexBrowser::class)->dataStatus($p, null))->toBe('Disputed');
});

/*
| Admin data centre
*/

it('keeps coverage and verification admin only, and counts real records only', function () {
    ($this->seedPublic)();
    foreach (['coverage', 'verification'] as $page) {
        $this->actingAs(User::factory()->create())->get(route("admin.investment-index.$page"))->assertForbidden();
    }
    auth()->logout();
    $this->get(route('admin.investment-index.coverage'))->assertRedirect();

    $s = app(Coverage::class)->summary();
    expect($s['totals']['projects'])->toBe(2)->and($s['totals']['legal_verified'])->toBe(2)->and($s['cities']['Lahore']['projects'])->toBe(2)->and($s['categories']['housing-societies']['projects'])->toBe(2);
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.investment-index.coverage'))->assertOk()->assertSee('Source registry')->assertSee('Read once')->assertSee('Test authority approved list');
    $this->get(route('admin.investment-index.verification'))->assertOk()->assertSee('Add a legal record');
});

it('adds a staff legal record as unverified; it counts only after a person verifies it, and a rejected one never does', function () {
    $p = new Project(['name' => 'Staff Checked Tower', 'category' => 'business_tower', 'city' => 'Karachi', 'status' => 'unverified', 'description' => 'A fixture tower used by the tests only.']);
    $p->forceFill(['is_published' => true, 'is_demo' => false])->save();
    $this->actingAs(User::factory()->admin()->create());
    $form = ['project_slug' => $p->slug, 'authority' => 'SBCA', 'record_type' => 'approval', 'status' => 'approved', 'reference' => 'NOC-1', 'source_url' => 'https://sbca.gos.pk/doc'];

    $this->post(route('admin.investment-index.legal.store'), $form)->assertSessionHas('status');
    $record = InvestmentLegalRecord::where('project_id', $p->id)->sole();
    expect($record->verification_status)->toBe('unverified');
    app(IndexEngine::class)->run('test');
    expect(app(IndexBrowser::class)->score($p)->legal_status)->toBe('not_verified');

    $this->post(route('admin.investment-index.review', ['legal', $record->id]), ['decision' => 'reject'])->assertSessionHasErrors('reason');
    $this->post(route('admin.investment-index.review', ['legal', $record->id]), ['decision' => 'verify'])->assertSessionHas('status');
    expect($record->fresh()->verification_status)->toBe('verified')->and($record->fresh()->verified_by)->not->toBeNull();
    app(IndexEngine::class)->run('test');
    expect(app(IndexBrowser::class)->score($p->fresh())->legal_status)->not->toBe('not_verified');

    $this->post(route('admin.investment-index.legal.store'), ['reference' => 'NOC-2'] + $form);
    $second = InvestmentLegalRecord::where('reference', 'NOC-2')->sole();
    $this->post(route('admin.investment-index.review', ['legal', $second->id]), ['decision' => 'reject', 'reason' => 'Not the same project.']);
    expect($second->fresh()->verification_status)->toBe('rejected')->and($second->fresh()->rejection_reason)->toBe('Not the same project.');
});

it('imports CSV rows safely: invalid rows listed, duplicates skipped, everything unverified, asking prices refused', function () {
    $p = new Project(['name' => 'Import Mall', 'category' => 'shopping_mall', 'city' => 'Lahore', 'status' => 'unverified', 'description' => 'A fixture mall used by the tests only.']);
    $p->forceFill(['is_published' => true, 'is_demo' => false])->save();
    $this->actingAs(User::factory()->admin()->create());
    $csv = "project_slug,factor,subject,value_numeric,value_level,unit,observed_on,source_name,source_type,source_url\n"
        ."{$p->slug},occupancy,,91,,percent,2026-06-01,Independent Surveyors,site_inspection,https://example.com/report\n"
        ."{$p->slug},occupancy,,91,,percent,2026-06-01,Independent Surveyors,site_inspection,https://example.com/report\n"
        ."no-such-project,occupancy,,91,,percent,2026-06-01,Independent Surveyors,site_inspection,https://example.com/report\n"
        ."{$p->slug},market_performance,,100,,,2026-06-01,A Portal,public_asking_prices,https://example.com/ad\n"
        ."{$p->slug},occupancy,,91\n";

    $this->post(route('admin.investment-index.import'), ['kind' => 'evidence', 'file' => UploadedFile::fake()->createWithContent('e.csv', $csv)])
        ->assertSessionHas('status', '1 added as unverified, 1 already on file, 3 rejected.');
    expect(InvestmentEvidence::where('project_id', $p->id)->sole()->verification_status)->toBe('unverified')->and(session('import_result')['errors'][0]['row'])->toBe(4);
    $this->get(route('admin.investment-index.verification'))->assertOk()->assertSee('Import Mall')->assertSee('Rejected rows');
});

/*
| Agents
*/

it('lets the Investment Analyst explain one project from stored evidence, with no score and no invented figures', function () {
    ($this->seedPublic)();
    $admin = User::factory()->admin()->create();

    $t = app(CentralAiOrchestrator::class)->submit('Is Model Enclave a good investment?', 'investment-analyst', $admin);
    $text = json_encode($t->result);
    expect($t->status)->toBe('completed')->and($t->toolCalls()->pluck('tool')->all())->toBe(['investment_index.project'])
        ->and($text)->toContain('Verified Approved')->toContain('has no published score')->toContain('Insufficient Verified Data')->toContain('example.gov.pk')->toContain('No verified evidence yet');

    expect(app(CentralAiOrchestrator::class)->submit('Tell me about River Gardens', 'investment-analyst', $admin)->toolCalls()->pluck('tool')->all())->toBe(['investment_index.project']);

    $none = app(CentralAiOrchestrator::class)->submit('Show the top shopping malls in the investment index', 'investment-analyst', $admin);
    expect($none->toolCalls()->pluck('tool')->first())->toBe('investment_index.leaderboard');
});

it('gives the coverage tool to the five data agents only, and reports actual counts through the Brain', function () {
    ($this->seedPublic)();
    foreach (['ceo', 'property-manager', 'investment-analyst', 'global-data-manager', 'technical-operations'] as $slug) {
        expect(AiAgent::where('slug', $slug)->sole()->tools)->toContain('investment_index.coverage');
    }
    expect(AiAgent::where('slug', 'hr-admin')->sole()->tools)->not->toContain('investment_index.coverage');

    $admin = User::factory()->admin()->create();
    $brain = app(CentralAiOrchestrator::class);
    $t = $brain->submit('Show the investment data coverage and source freshness', 'global-data-manager', $admin);
    expect($t->status)->toBe('completed')->and($t->toolCalls()->pluck('tool')->all())->toBe(['investment_index.coverage'])
        ->and(implode(' ', $t->result['findings'] ?? $t->result['summary']))->toContain('2 genuine projects')->and(implode(' ', $t->result['limitations']))->toContain('not all of Pakistan');
    expect($brain->submit('Did any data collection fail?', 'technical-operations', $admin)->toolCalls()->pluck('tool')->all())->toBe(['investment_index.coverage']);
    expect($brain->submit('Are there duplicate project records?', 'property-manager', $admin)->toolCalls()->pluck('tool')->all())->toBe(['investment_index.coverage']);
    expect(AiTask::where('status', 'completed')->count())->toBe(3);
});
