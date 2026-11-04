<?php

use App\Models\AiTask;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentIndexChange;
use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentLegalRecord;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use App\Services\Agents\CentralAiOrchestrator;
use App\Services\InvestmentIndex\EvidenceSubmissions;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Services\InvestmentIndex\IndexEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Meezan Investment Index, Phase 3: the public pages (/investment-rankings):
| five leaderboards, the Overall Index, project profiles, methodology, and
| the admin weights editor. Fixtures are real-data projects with verified
| evidence; demo data never appears.
*/

function pgSource(string $type, string $publisher, string $url = 'https://example.gov.pk/list'): MarketSource
{
    $s = MarketSource::firstOrCreate(['name' => "$publisher ($type)", 'type' => $type], ['publisher' => $publisher, 'reliability' => 'high', 'url' => $url]);
    $s->forceFill(['is_active' => true])->save();

    return $s;
}

function pgProject(string $name, string $category, array $attributes = []): Project
{
    $p = new Project(array_merge(['name' => $name, 'category' => $category, 'city' => 'Lahore', 'status' => 'completed', 'developer' => 'Pages Test Developers',
        'description' => 'A real-data fixture used only by the automated tests.'], $attributes));
    $p->forceFill(['is_published' => $attributes['is_published'] ?? true, 'is_demo' => $attributes['is_demo'] ?? false])->save();

    return $p;
}

function pgEvidence(Project $p, string $factor, MarketSource $s, float|string $value, array $extra = []): void
{
    $e = new InvestmentEvidence(array_merge(['project_id' => $p->id, 'factor' => $factor, 'source_id' => $s->id, 'value_numeric' => is_string($value) ? null : $value,
        'value_level' => is_string($value) ? $value : null, 'observed_on' => now()->subMonth(), 'source_url' => $s->url.'#'.$p->id.'-'.$factor, 'collected_at' => now()], $extra));
    $e->forceFill(['verification_status' => 'verified'])->save();
}

function pgLegal(Project $p, string $status = 'approved', string $type = 'approval', array $extra = []): void
{
    $official = pgSource('official_record', 'Lahore Development Authority', 'https://lda.gop.pk/approved-schemes');
    $r = new InvestmentLegalRecord(array_merge(['project_id' => $p->id, 'authority' => 'LDA', 'record_type' => $type, 'status' => $status, 'reference' => 'LDA list entry '.$p->id,
        'issued_on' => now()->subYears(2), 'source_id' => $official->id, 'source_url' => 'https://lda.gop.pk/approved-schemes#'.$p->id, 'collected_at' => now()], $extra));
    $r->forceFill(['verification_status' => 'verified'])->save();
}

/** A housing society with approved legal status and 70% of the weight verified. */
function pgSociety(string $name, float $progress): Project
{
    $p = pgProject($name, 'residential_society');
    pgLegal($p);
    pgEvidence($p, 'development_progress', pgSource('official_record', 'Lahore Development Authority', 'https://lda.gop.pk/approved-schemes'), $progress);
    pgEvidence($p, 'occupancy', pgSource('site_inspection', 'Independent Surveyors'), 70);
    pgEvidence($p, 'location', pgSource('site_inspection', 'Independent Surveyors'), 'good');
    pgEvidence($p, 'operational_status', pgSource('site_inspection', 'Independent Surveyors'), 'operational');

    return $p;
}

beforeEach(function () {
    $this->a = pgSociety('Pages Garden Town', 95);
    $this->b = pgSociety('Pages Model Town', 60);
    $this->c = pgSociety('Pages Canal View', 40);
    // A mall documented by its owner (staff-verified): ranked, its documents are not linked publicly.
    $this->mall = pgProject('Pages Fortress Mall', 'shopping_mall');
    pgLegal($this->mall);
    $owner = pgSource('owner_document', 'Pages Mall Management', 'https://meezan.test/admin/investment-index/submissions/1');
    foreach (['operational_performance' => 'strong', 'tenant_quality' => 'good'] as $f => $v) {
        pgEvidence($this->mall, $f, $owner, $v);
    }
    pgEvidence($this->mall, 'occupancy', $owner, 91);
    pgEvidence($this->mall, 'rental_income', $owner, 7.25);
    $this->excluded = pgSociety('Pages Illegal Scheme', 90);
    pgLegal($this->excluded, 'illegal', 'illegal_listing', ['issued_on' => now()->subMonth(), 'severity' => 'serious', 'reference' => 'Illegal schemes list']);
    $this->thin = pgProject('Pages Thin Tower', 'business_tower');
    app(IndexEngine::class)->run('test');
});

it('shows the five leaderboards and the Overall Index on the rankings home', function () {
    $this->get(route('rankings.index'))->assertOk()
        ->assertSee('Top Housing Societies')->assertSee('Top Commercial Plazas')->assertSee('Top Apartment Buildings')->assertSee('Top Shopping Malls')->assertSee('Top Business Towers')
        ->assertSee('Overall Meezan Investment Index')->assertSeeInOrder(['Pages Garden Town', 'Pages Model Town', 'Pages Canal View'])
        ->assertSee('Pages Fortress Mall')->assertSee('Insufficient Verified Data')->assertSee('No paid or sponsored ranking')
        ->assertDontSee('Pages Illegal Scheme')->assertSee('data-search-tabs', false);
});

it('ranks a category Pakistan-wide, lists the others by status without a score, and filters by city', function () {
    $html = $this->get(route('rankings.category', 'housing-societies'))->assertOk()
        ->assertSeeInOrder(['Pages Garden Town', 'Pages Model Town', 'Pages Canal View', 'Assessed, not ranked', 'Pages Illegal Scheme'])
        ->assertSee('Excluded from recommendations')->assertSee('Legal approval')->assertSee('25%')->assertDontSee('noindex', false)->getContent();
    expect(substr_count($html, 'href="'.route('rankings.project', $this->excluded).'"'))->toBe(1);

    $this->get(route('rankings.category', ['housing-societies', 'city' => 'Karachi']))->assertOk()->assertSee('in Karachi')->assertSee('Insufficient Verified Data')->assertDontSee('Pages Garden Town');
    $this->get(route('rankings.category', 'business-towers'))->assertOk()->assertSee('Insufficient Verified Data')->assertSee('Pages Thin Tower')->assertSee('noindex', false);
    $this->get('/investment-rankings/plots')->assertNotFound();
});

it('builds the Overall Index only from categories with enough ranked peers', function () {
    $this->get(route('rankings.overall'))->assertOk()->assertSeeInOrder(['Pages Garden Town', 'Pages Model Town', 'Pages Canal View'])
        ->assertDontSee('Pages Fortress Mall')->assertSee('at least 3 ranked projects');
});

it('shows every required fact on a project profile, with sources and ranking changes', function () {
    $this->get(route('rankings.project', $this->a))->assertOk()
        ->assertSee('Pages Garden Town')->assertSee('Housing Societies')->assertSee('Lahore')->assertSee('Pages Test Developers')
        ->assertSee('Legal verification')->assertSee('Approved')->assertSee('Operational status')->assertSee('Operational')
        ->assertSee('Development progress')->assertSee('95%')->assertSee('Investment score')->assertSee('Data confidence')->assertSee('Risk')
        ->assertSee('Verified rental yield')->assertSee('Historical performance')->assertSee('Ranking position')->assertSee('#1')
        ->assertSee('Last verification')->assertSee('Original data sources')->assertSee('Lahore Development Authority')
        ->assertSee('https://lda.gop.pk/approved-schemes#'.$this->a->id, false)->assertSee('Ranking changes')->assertSee('First assessment')
        ->assertSee('Score breakdown')->assertDontSee('noindex', false);

    // Owner documents are named, never linked (they are private staff pages).
    $this->get(route('rankings.project', $this->mall))->assertOk()->assertSee('Pages Mall Management')->assertSee('Documents checked by Meezan Property staff')
        ->assertDontSee('/admin/investment-index/submissions', false)->assertSee('7.25% gross');
});

it('never shows a score for a project that is not ranked, and refuses demo, unpublished and out-of-scope projects', function () {
    $this->get(route('rankings.project', $this->excluded))->assertOk()->assertSee('Excluded from recommendations')->assertSee('Illegal schemes list')->assertSee('noindex', false);
    $html = $this->get(route('rankings.project', $this->thin))->assertOk()->assertSee('Insufficient Verified Data')->assertSee('Not verified')->getContent();
    expect($html)->not->toMatch('/Investment score<\/dt><dd[^>]*>\d/');

    $this->get(route('rankings.project', pgProject('Pages Demo Mall', 'shopping_mall', ['is_demo' => true])))->assertNotFound();
    $this->get(route('rankings.project', pgProject('Pages Draft Mall', 'shopping_mall', ['is_published' => false])))->assertNotFound();
    $this->get(route('rankings.project', pgProject('Pages Mixed Use', 'mixed_use')))->assertNotFound();
});

it('lists ranked pages in the sitemap and leaves out the rest', function () {
    $xml = $this->get(route('sitemap.show', 'pages'))->assertOk()->getContent();

    expect($xml)->toContain(route('rankings.index'))->toContain(route('rankings.methodology'))->toContain(route('rankings.category', 'housing-societies'))
        ->toContain(route('rankings.category', 'shopping-malls'))->toContain(route('rankings.overall'))->toContain(route('rankings.project', $this->a))
        ->not->toContain(route('rankings.category', 'business-towers'))->not->toContain(route('rankings.project', $this->excluded))
        ->not->toContain(route('rankings.project', $this->thin))->not->toContain('investment-rankings/lahore');
});

it('lets admins change weights only through a new version, which recalculates the Index and explains it', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.investment-index.methodology'))->assertOk()->assertSee('Active weights (version 1)');
    $this->post(route('admin.investment-index.methodology.draft'))->assertSessionHas('status');
    $draft = InvestmentIndexMethodology::where('status', 'draft')->sole();

    $weights = InvestmentIndexMethodology::configWeights();
    $bad = $weights;
    $bad['housing-societies']['occupancy'] = 50;
    $this->patch(route('admin.investment-index.methodology.update', $draft), ['weights' => $bad, 'notes' => 'x'])->assertSessionHasErrors('weights.housing-societies');
    $zeroLegal = $weights;
    $zeroLegal['housing-societies']['legal_approval'] = 0;
    $zeroLegal['housing-societies']['occupancy'] = 40;
    $this->patch(route('admin.investment-index.methodology.update', $draft), ['weights' => $zeroLegal, 'notes' => 'x'])->assertSessionHasErrors('weights.housing-societies.legal_approval');

    $new = $weights;
    $new['housing-societies']['development_progress'] = 10;
    $new['housing-societies']['occupancy'] = 25;
    $this->patch(route('admin.investment-index.methodology.update', $draft), ['weights' => $new, 'notes' => 'Occupancy proves delivery better.'])->assertSessionHas('status');
    $this->post(route('admin.investment-index.methodology.activate', $draft))->assertSessionHas('status');

    expect(InvestmentIndexMethodology::active()->version)->toBe(2)->and(InvestmentIndexMethodology::where('version', 1)->value('status'))->toBe('retired')
        ->and(InvestmentIndexRun::latestPublished()->methodology_version)->toBe(2)
        ->and(InvestmentIndexChange::where('project_id', $this->a->id)->latest('id')->first()->reasons)->toContain('Methodology changed from version 1 to 2.');
    auth()->logout();
    $this->get(route('rankings.methodology'))->assertOk()->assertSee('Methodology version 2')->assertSee('25%');
    $this->actingAs(User::factory()->create())->post(route('admin.investment-index.methodology.draft'))->assertForbidden();
});

it('has the Investment Analyst report the Index leaderboards by default', function () {
    $task = app(CentralAiOrchestrator::class)->submit('Which shopping malls are the best investment?', 'investment-analyst', User::factory()->admin()->create());

    expect($task->status)->toBe('completed')->and($task->result['title'])->toBe('Meezan Investment Index — Shopping Malls')
        ->and(json_encode($task->result))->toContain('Pages Fortress Mall')->toContain('Investment Index leaderboards')
        ->and(AiTask::find($task->id)->toolCalls()->pluck('tool')->all())->toContain('investment_index.leaderboard');
});

it('can verify a submission straight after it is stored (QA regression)', function () {
    Storage::fake('local');
    $s = app(EvidenceSubmissions::class)->store($this->thin, User::factory()->create(), ['kind' => 'evidence', 'factor' => 'occupancy', 'percent' => 80,
        'organisation' => 'Tower Ltd', 'submitter_role' => 'Director', 'observed_on' => now()->toDateString(), 'declaration' => '1', 'document_type' => 'owner_statement'],
        [UploadedFile::fake()->create('register.pdf', 40, 'application/pdf')]);

    expect($s->status)->toBe('pending');
    app(EvidenceSubmissions::class)->verify($s, User::factory()->admin()->create());
    expect($s->fresh()->status)->toBe('verified');
});

it('links public sources only over http(s) and never to the admin area', function () {
    expect(IndexBrowser::safeUrl('https://lda.gop.pk/approved-schemes#1'))->toBe('https://lda.gop.pk/approved-schemes#1')
        ->and(IndexBrowser::safeUrl('javascript:alert(1)'))->toBeNull()
        ->and(IndexBrowser::safeUrl('https://meezanproperty.com/admin/investment-index/submissions/4'))->toBeNull()
        ->and(IndexBrowser::safeUrl(null))->toBeNull();
});

it('offers only the Index commercial categories in the search bar', function () {
    $html = $this->get('/commercial')->assertOk()->getContent();

    expect($html)->toContain('Shopping Malls (rankings)')->toContain('Commercial Plazas (rankings)')->toContain('Business Towers (rankings)')
        ->not->toContain('Office Spaces (rankings)')->not->toContain('Commercial Shops (rankings)')->not->toContain('Commercial Plots (rankings)');
});
