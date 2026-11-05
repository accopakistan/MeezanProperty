<?php

use App\Models\InvestmentCollectionRun;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentEvidenceSubmission;
use App\Models\InvestmentException;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentLegalRecord;
use App\Models\InvestmentRegistryEntry;
use App\Models\MarketSource;
use App\Models\Project;
use App\Models\User;
use App\Services\InvestmentIndex\Collection\CollectionService;
use App\Services\InvestmentIndex\Collection\OfficialStatus;
use App\Services\InvestmentIndex\Collection\SchemeNames;
use App\Services\InvestmentIndex\EvidenceSubmissions;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
| Meezan Investment Index, Phase 2: authorized collection of official
| lists (robots.txt, access review, exceptions), matching to projects,
| verified legal records, and owner documents checked by staff.
*/

beforeEach(function () {
    config(['investment_index.collection.delay_ms' => 0]);
    Storage::fake('local');
});

/** An Inertia page as the LDA site serves it. */
function inertiaPage(array $schemes, ?int $lastPage = null, int $page = 1): string
{
    $props = ['schemes' => $lastPage ? ['data' => $schemes, 'current_page' => $page, 'last_page' => $lastPage] : $schemes];

    return '<html><body><div id="app" data-page="'.htmlspecialchars(json_encode(['component' => 'X', 'props' => $props]), ENT_QUOTES).'"></div></body></html>';
}

/** @param  array<string, string|callable>  $pages  path(?query) => body */
function fakeSite(array $pages, string|int $robots = ''): void
{
    // A fresh client each time: Laravel's fakes stack, and the first would answer every request.
    Http::swap(new Factory(app('events')));
    Http::fake(function (Request $request) use ($pages, $robots) {
        $url = parse_url($request->url());
        $path = ($url['path'] ?? '/').(isset($url['query']) ? '?'.$url['query'] : '');
        if ($path === '/robots.txt') {
            return is_int($robots) ? Http::response('', $robots) : Http::response($robots);
        }

        return isset($pages[$path]) ? Http::response(is_callable($pages[$path]) ? $pages[$path]() : $pages[$path]) : Http::response('not found', 404);
    });
}

function officialSource(string $collector, bool $authorized = true): MarketSource
{
    $s = MarketSource::create(['name' => "Test $collector", 'type' => 'official_record', 'publisher' => 'Lahore Development Authority', 'reliability' => 'high']);
    $s->forceFill(['collector' => $collector, 'is_active' => $authorized, 'access_basis' => $authorized ? 'public_record' : null,
        'authorized_at' => $authorized ? now() : null, 'access_note' => 'Test fixture'])->save();

    return $s;
}

function society(string $name, string $city = 'Lahore'): Project
{
    $p = new Project(['name' => $name, 'category' => 'residential_society', 'city' => $city, 'status' => 'completed', 'description' => 'A real-data fixture used only by the automated tests.']);
    $p->forceFill(['is_published' => true, 'is_demo' => false])->save();

    return $p;
}

function approvedRow(int $id, string $name, string $stage = 'Finally Approved', string $date = '2019-07-25'): array
{
    return ['id' => $id, 'scheme_name' => $name, 'location' => 'Raiwind Road, Lahore. rn', 'approval_stage' => $stage, 'date_of_approval' => $date];
}

it('reads official approval stages conservatively', function () {
    expect(OfficialStatus::for('approved', 'Finally Appoved', 'lda'))->toBe('approved')
        ->and(OfficialStatus::for('approved', 'Final Approval Granted on 23.12.2021', 'lda'))->toBe('approved')
        ->and(OfficialStatus::for('approved', 'Technical Approval Granted dated 13-06-2020 Final Approval is pending', 'lda'))->toBe('partially_approved')
        ->and(OfficialStatus::for('approved', 'TECHNICALLY APPROVED', 'lda'))->toBe('partially_approved')
        ->and(OfficialStatus::for('approved', 'Approved from TMA', 'lda'))->toBe('approved')
        ->and(OfficialStatus::for('approved', '', 'lda'))->toBeNull()
        ->and(OfficialStatus::for('approved', 'Near UET Society, Lahore', 'lda'))->toBeNull()
        ->and(OfficialStatus::for('illegal', 'active'))->toBe('illegal')
        ->and(OfficialStatus::date('31.03.2011 n(Revised Plan)')->toDateString())->toBe('2011-03-31')
        ->and(OfficialStatus::date('20.07.09')->toDateString())->toBe('2009-07-20')
        ->and(OfficialStatus::date('1988'))->toBeNull();
});

it('matches scheme names only when the phases agree', function () {
    // As the LDA page serves it: UTF-8 curly quotes read as Windows-1252.
    expect(SchemeNames::clean("\u{e2}\u{20ac}\u{153}Green Palms\u{e2}\u{20ac}\u{9d} Housing Scheme"))->toBe('Green Palms Housing Scheme')
        ->and(SchemeNames::similarity('Abdalians Cooperative Housing Society phase-I', 'Abdalians Phase 1'))->toBe(1.0)
        ->and(SchemeNames::similarity('AL GHANI GARDEN PHASE-II', 'Al Ghani Garden Phase I'))->toBe(0.0)
        ->and(SchemeNames::similarity('Nasheman Iqbal Ph-II', 'Nasheman Iqbal Phase 2'))->toBe(1.0)
        ->and(SchemeNames::similarity('Lake City Housing Scheme', 'Lake City'))->toBe(1.0)
        ->and(SchemeNames::similarity('Lake City Phase 2', 'Lake City'))->toBeLessThan(0.92)
        ->and(SchemeNames::clean('Allama Iqbal Town'))->toBe('Allama Iqbal Town')
        ->and(SchemeNames::clean('Abdullah Garden LSD,'))->toBe('Abdullah Garden LSD')
        ->and(SchemeNames::clean('Lake City Bella Vista (Approval of Unapproved Pockets)'))->toBe('Lake City Bella Vista (Approval of Unapproved Pockets)');
});

it('collects an authorized official list into verified legal records and the index', function () {
    $green = society('Green Palms');
    $other = society('Unlisted Gardens');
    fakeSite(['/approved-schemes' => inertiaPage([approvedRow(360, '“Green Palms” Housing Scheme', 'Technically Approved', '2022-12-23'), approvedRow(58, 'Abid Town')])]);

    $run = app(CollectionService::class)->collect(officialSource('lda-approved'));

    expect($run->status)->toBe('completed')->and($run->entries_found)->toBe(2)->and($run->matched)->toBe(1)->and($run->legal_records_created)->toBe(1);
    $record = InvestmentLegalRecord::where('project_id', $green->id)->sole();
    expect($record->verification_status)->toBe('verified')->and($record->status)->toBe('partially_approved')->and($record->authority)->toBe('LDA')
        ->and($record->issued_on->toDateString())->toBe('2022-12-23')->and($record->source_url)->toBe('https://lda.gop.pk/approved-schemes#360')
        ->and($record->verification_method)->toBe('official_registry')->and($record->excerpt)->toContain('Technically Approved')
        ->and(InvestmentRegistryEntry::where('external_key', '58')->value('match_status'))->toBe('unmatched');

    // The index picked it up automatically: the legal status is verified, the unlisted project's is not.
    $scores = InvestmentIndexRun::latestPublished()->scores()->get()->keyBy('project_id');
    expect($scores[$green->id]->legal_status)->toBe('partially_approved')->and($scores[$other->id]->legal_status)->toBe('not_verified');

    // Collecting again adds nothing; the request identified the bot.
    expect(app(CollectionService::class)->collect(MarketSource::firstWhere('collector', 'lda-approved'))->legal_records_created)->toBe(0)
        ->and(InvestmentLegalRecord::count())->toBe(1);
    Http::assertSent(fn (Request $r) => str_starts_with($r->header('User-Agent')[0], 'MeezanPropertyBot/'));
});

it('excludes a project on an official illegal list, reading every page', function () {
    $p = society('Sajid Garden');
    fakeSite([
        '/illegal-schemes' => inertiaPage([['id' => 1, 'scheme_name' => 'Sajid Garden HS', 'illegal_status' => 'active', 'created_at' => '2026-05-29T18:45:20Z', 'directorate' => 'DMP-III']], 2),
        '/illegal-schemes?page=2' => inertiaPage([['id' => 2, 'scheme_name' => 'Ghani Bagh', 'illegal_status' => 'active', 'created_at' => '2026-05-29T18:45:20Z'],
            ['id' => 3, 'scheme_name' => 'Old Listing', 'illegal_status' => 'inactive']], 2, 2),
    ]);

    $run = app(CollectionService::class)->collect(officialSource('lda-illegal'));

    expect($run->entries_found)->toBe(2)->and($run->pages_fetched)->toBeGreaterThanOrEqual(2)
        ->and(InvestmentLegalRecord::where('project_id', $p->id)->sole())->toMatchArray(['record_type' => 'illegal_listing', 'status' => 'illegal', 'severity' => 'serious', 'reference' => 'DMP-III'])
        ->and(InvestmentIndexRun::latestPublished()->scores()->where('project_id', $p->id)->value('status'))->toBe('excluded');
});

it('flags conflicting official records and keeps the project excluded', function () {
    $p = society('Sajid Garden');
    fakeSite(['/approved-schemes' => inertiaPage([approvedRow(9, 'Sajid Garden')]),
        '/illegal-schemes' => inertiaPage([['id' => 1, 'scheme_name' => 'Sajid Garden HS', 'illegal_status' => 'active', 'created_at' => '2026-05-29T00:00:00Z']], 1)]);
    app(CollectionService::class)->collect(officialSource('lda-approved'));
    app(CollectionService::class)->collect(officialSource('lda-illegal'));

    expect(InvestmentException::where('type', 'legal_conflict')->where('project_id', $p->id)->exists())->toBeTrue()
        ->and(InvestmentIndexRun::latestPublished()->scores()->where('project_id', $p->id)->value('status'))->toBe('excluded');
});

it('never reads a source that is not authorized or that robots.txt forbids', function () {
    fakeSite(['/approved-schemes' => inertiaPage([approvedRow(1, 'Abid Town')])]);
    $run = app(CollectionService::class)->collect(officialSource('lda-approved', authorized: false));
    expect($run->status)->toBe('blocked');
    Http::assertNothingSent();

    fakeSite(['/approved-schemes' => inertiaPage([approvedRow(1, 'Abid Town')])], "User-agent: *\nDisallow: /approved");
    $run = app(CollectionService::class)->collect(officialSource('lda-approved'));
    expect($run->status)->toBe('blocked')->and($run->error)->toContain('robots.txt')
        ->and(InvestmentException::where('type', 'source_access')->exists())->toBeTrue()->and(InvestmentRegistryEntry::count())->toBe(0);

    // robots.txt unreadable because of a server error: not read either.
    fakeSite(['/approved-schemes' => inertiaPage([approvedRow(1, 'Abid Town')])], 503);
    expect(app(CollectionService::class)->collect(MarketSource::latest('id')->first())->status)->toBe('blocked');
});

it('treats a changed page or a shrunken list as an exception, never as removals', function () {
    $source = officialSource('lda-approved');
    $rows = collect(range(1, 10))->map(fn ($i) => approvedRow($i, "Scheme Number $i"))->all();
    fakeSite(['/approved-schemes' => inertiaPage($rows)]);
    app(CollectionService::class)->collect($source);

    fakeSite(['/approved-schemes' => inertiaPage(array_slice($rows, 0, 3))]);
    $run = app(CollectionService::class)->collect($source);
    expect($run->status)->toBe('partial')->and($run->entries_removed)->toBe(0)->and(InvestmentRegistryEntry::whereNotNull('removed_at')->count())->toBe(0)
        ->and(InvestmentException::where('type', 'format_change')->exists())->toBeTrue();

    fakeSite(['/approved-schemes' => '<html><body>Maintenance</body></html>']);
    expect(app(CollectionService::class)->collect($source)->status)->toBe('failed')
        ->and(InvestmentException::where('type', 'format_change')->where('payload->key', 'format')->exists())->toBeTrue();
});

it('asks staff when a matched scheme leaves a list, and lets staff withdraw its record', function () {
    $p = society('Abid Town');
    $source = officialSource('lda-approved');
    $rows = collect(range(1, 4))->map(fn ($i) => approvedRow($i, "Scheme Number $i"))->push(approvedRow(99, 'Abid Town'))->all();
    fakeSite(['/approved-schemes' => inertiaPage($rows)]);
    app(CollectionService::class)->collect($source);

    fakeSite(['/approved-schemes' => inertiaPage(array_slice($rows, 0, 4))]);
    $run = app(CollectionService::class)->collect($source);
    $exception = InvestmentException::where('type', 'removed_from_list')->sole();
    expect($run->entries_removed)->toBe(1)->and($exception->project_id)->toBe($p->id)
        // Leaving a list is not read as a cancellation: the record stays until staff decide.
        ->and(InvestmentLegalRecord::where('project_id', $p->id)->value('verification_status'))->toBe('verified');

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.investment-index.exceptions.resolve', $exception), ['outcome' => 'resolved', 'resolution' => 'LDA confirmed the approval was withdrawn.', 'withdraw_record' => 1])
        ->assertSessionHas('status');
    expect(InvestmentLegalRecord::where('project_id', $p->id)->value('verification_status'))->toBe('superseded')
        ->and(InvestmentIndexRun::latestPublished()->scores()->where('project_id', $p->id)->value('legal_status'))->toBe('not_verified');
});

it('sends uncertain matches and unreadable stages to staff, and matches new projects automatically', function () {
    society('Lake City Phase 1');
    society('Lake City Phase 2');
    fakeSite(['/approved-schemes' => inertiaPage([approvedRow(1, 'Lake City Housing Scheme'), approvedRow(2, 'Hassan Town', ''), approvedRow(3, 'Abid Town')])]);
    app(CollectionService::class)->collect(officialSource('lda-approved'));

    $lake = InvestmentRegistryEntry::where('external_key', '1')->sole();
    expect($lake->match_status)->toBe('ambiguous')->and(InvestmentException::where('type', 'ambiguous_match')->count())->toBe(1)
        ->and(InvestmentException::where('type', 'unrecognised_status')->sole()->title)->toContain('Hassan Town')
        ->and(InvestmentLegalRecord::count())->toBe(0);

    // Staff decide the ambiguous one; a project added later matches its entry by itself.
    $admin = User::factory()->admin()->create();
    $phase1 = Project::firstWhere('name', 'Lake City Phase 1');
    $this->actingAs($admin)->post(route('admin.investment-index.registry.match', $lake), ['project_id' => $phase1->id])->assertSessionHas('status');
    expect($lake->fresh()->match_status)->toBe('manual')->and(InvestmentLegalRecord::where('project_id', $phase1->id)->exists())->toBeTrue()
        ->and(InvestmentException::where('type', 'ambiguous_match')->value('status'))->toBe('resolved');

    $abid = society('Abid Town');
    expect(InvestmentRegistryEntry::where('external_key', '3')->value('project_id'))->toBe($abid->id)
        ->and(InvestmentLegalRecord::where('project_id', $abid->id)->value('status'))->toBe('approved');
});

it('accepts owner documents but counts them only after a staff check', function () {
    $plaza = new Project(['name' => 'Evidence Plaza', 'category' => 'commercial_plaza', 'city' => 'Lahore', 'status' => 'completed', 'description' => 'A real-data fixture used only by the automated tests.']);
    $plaza->forceFill(['is_published' => true, 'is_demo' => false])->save();
    $owner = User::factory()->create();
    $file = UploadedFile::fake()->create('rent-roll.pdf', 200, 'application/pdf');

    $this->get(route('investment-index.evidence.create', $plaza))->assertRedirect(route('login'));
    $this->actingAs($owner)->get(route('investment-index.evidence.create', $plaza))->assertOk()->assertSee('Submit evidence for Evidence Plaza');
    $this->actingAs($owner)->post(route('investment-index.evidence.store', $plaza), [
        'kind' => 'evidence', 'organisation' => 'Evidence Plaza Management', 'submitter_role' => 'Finance manager', 'factor' => 'rental_income',
        'annual_rent' => 6_000_000, 'property_value' => 80_000_000, 'value_basis' => 'independent_valuation', 'document_type' => 'owner_statement',
        'observed_on' => now()->subMonth()->toDateString(), 'declaration' => '1', 'files' => [$file],
    ])->assertRedirect()->assertSessionHas('status');

    $s = InvestmentEvidenceSubmission::sole();
    expect($s->status)->toBe('pending')->and($s->value_numeric)->toBe(7.5)->and(InvestmentEvidence::count())->toBe(0);
    Storage::disk('local')->assertExists($s->files[0]['path']);
    $this->actingAs($owner)->get(route('admin.investment-index.submissions.file', [$s, 0]))->assertForbidden();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.investment-index.submissions.show', $s))->assertOk()->assertSee('6,000,000')->assertSee('7.5% gross');
    $this->actingAs($admin)->get(route('admin.investment-index.submissions.file', [$s, 0]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($admin)->post(route('admin.investment-index.submissions.review', $s), ['decision' => 'verify'])->assertRedirect();

    $e = InvestmentEvidence::sole();
    expect($e->verification_status)->toBe('verified')->and($e->value_numeric)->toBe(7.5)->and($e->source->type)->toBe('owner_document')
        ->and($e->source->publisher)->toBe('Evidence Plaza Management')->and($e->verification_method)->toBe('staff_document_check')
        ->and($e->verified_by)->toBe($admin->id)->and($e->source_url)->toBe(route('admin.investment-index.submissions.show', $s))
        ->and(InvestmentIndexRun::latestPublished()->scores()->where('project_id', $plaza->id)->first()->factors['rental_income']['value'])->toBe('7.5% gross');
});

it('keeps owner evidence within its limits', function () {
    $tower = new Project(['name' => 'Limits Tower', 'category' => 'business_tower', 'city' => 'Lahore', 'status' => 'completed', 'description' => 'A real-data fixture used only by the automated tests.']);
    $tower->forceFill(['is_published' => true, 'is_demo' => false])->save();
    $demo = new Project(['name' => 'Demo Tower', 'category' => 'business_tower', 'city' => 'Lahore', 'status' => 'completed', 'description' => 'A demo fixture used only by the automated tests.']);
    $demo->forceFill(['is_published' => true, 'is_demo' => true])->save();
    $owner = User::factory()->create();
    $base = ['kind' => 'evidence', 'organisation' => 'Limits Tower Ltd', 'submitter_role' => 'Director', 'observed_on' => now()->toDateString(), 'declaration' => '1',
        'files' => [UploadedFile::fake()->create('cert.pdf', 50, 'application/pdf')]];

    // Structural safety needs an independent certificate; location is not something an owner can certify.
    $this->actingAs($owner)->post(route('investment-index.evidence.store', $tower), $base + ['factor' => 'structural_safety', 'level' => 'certified', 'document_type' => 'owner_statement'])
        ->assertSessionHasErrors('document_type');
    $this->actingAs($owner)->post(route('investment-index.evidence.store', $tower), $base + ['factor' => 'location', 'level' => 'excellent', 'document_type' => 'owner_statement'])
        ->assertSessionHasErrors('factor');
    $this->actingAs($owner)->post(route('investment-index.evidence.store', $tower), array_merge($base, ['factor' => 'occupancy', 'percent' => 90, 'document_type' => 'owner_statement', 'declaration' => null]))
        ->assertSessionHasErrors('declaration');
    $this->actingAs($owner)->get(route('investment-index.evidence.create', $demo))->assertNotFound();

    // A rejected submission never counts, and the submitter sees why.
    $this->actingAs($owner)->post(route('investment-index.evidence.store', $tower), $base + ['factor' => 'occupancy', 'percent' => 90, 'document_type' => 'owner_statement'])->assertSessionHas('status');
    $s = InvestmentEvidenceSubmission::sole();
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.investment-index.submissions.review', $s), ['decision' => 'reject'])->assertSessionHasErrors('note');
    app(EvidenceSubmissions::class)->reject($s, User::factory()->admin()->create(), 'The register does not name the building.');
    expect(InvestmentEvidence::count())->toBe(0);
    $this->actingAs($owner)->get(route('investment-index.evidence.create', $tower))->assertSee('The register does not name the building.');
});

it('shows the admin screens and requires an access review before collecting', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.investment-index.sources.setup'))->assertSessionHas('status');
    $source = MarketSource::firstWhere('collector', 'lda-approved');
    expect($source->is_active)->toBeFalse()->and($source->isCollectable())->toBeFalse()->and(MarketSource::whereNotNull('collector')->count())->toBe(count(array_filter(config('investment_index.collectors'), fn ($p) => ! ($p['configurable'] ?? false))));

    foreach (['overview', 'sources', 'registry', 'exceptions', 'submissions'] as $page) {
        $this->get(route('admin.investment-index.'.$page))->assertOk();
    }
    $this->post(route('admin.investment-index.sources.collect', $source))->assertSessionHas('error');
    $this->patch(route('admin.investment-index.sources.authorize', $source), ['access_basis' => 'public_record', 'access_note' => 'Checked'])->assertSessionHasErrors('confirm');
    $this->patch(route('admin.investment-index.sources.authorize', $source), ['access_basis' => 'public_record', 'access_note' => 'Public list; robots.txt allows.', 'confirm' => '1'])->assertSessionHas('status');
    expect($source->fresh()->isCollectable())->toBeTrue();

    $this->actingAs(User::factory()->create())->get(route('admin.investment-index.overview'))->assertForbidden();
});

it('keeps collection times fixed when a record is later updated', function () {
    $p = society('Fixed Time Gardens');
    fakeSite(['/approved-schemes' => inertiaPage([approvedRow(5, 'Fixed Time Gardens')])]);
    app(CollectionService::class)->collect(officialSource('lda-approved'));
    $record = InvestmentLegalRecord::where('project_id', $p->id)->sole();
    $collected = $record->collected_at->toDateTimeString();

    $this->travel(2)->days();
    $record->forceFill(['rejection_reason' => 'note'])->save();
    expect($record->fresh()->collected_at->toDateTimeString())->toBe($collected)
        ->and(InvestmentCollectionRun::count())->toBe(1);
});
