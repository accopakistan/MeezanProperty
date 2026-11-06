<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Jobs\RecalculateInvestmentIndex;
use App\Models\Area;
use App\Models\InvestmentIndexMethodology;
use App\Models\InvestmentIndexRun;
use App\Models\InvestmentIndexScore;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use App\Services\InvestmentIndex\IndexBrowser;
use App\Support\SampleContent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/*
| Each public category leaderboard shows at most 10 ranked projects, with an honest
| heading; and a listing is published only on a recorded publication basis.
*/

/** A published run with $ranked ranked housing societies (scores 90, 89, …), plus $other unranked ones. */
function t10Run(int $ranked, int $other = 0, string $category = 'housing-societies'): InvestmentIndexRun
{
    Bus::fake([RecalculateInvestmentIndex::class]); // a project save would otherwise publish a newer run over this one
    $run = new InvestmentIndexRun;
    $run->forceFill(['methodology_id' => InvestmentIndexMethodology::current()->id, 'methodology_version' => 1, 'trigger' => 'test', 'status' => 'published',
        'published_at' => now(), 'started_at' => now(), 'finished_at' => now()])->save();
    foreach (range(1, $ranked + $other) as $i) {
        $project = new Project(['name' => sprintf('Top Ten Society %02d', $i), 'city' => $i % 2 ? 'Lahore' : 'Karachi', 'category' => 'residential_society', 'status' => 'unverified', 'description' => 'A test project.']);
        $project->forceFill(['is_published' => true, 'is_demo' => false])->save();
        (new InvestmentIndexScore)->forceFill(['run_id' => $run->id, 'project_id' => $project->id, 'category' => $category, 'factors' => [], 'risk_class' => 'low',
            'legal_status' => 'approved', 'confidence_score' => 80, 'confidence_level' => 'high', 'coverage' => 0.8,
            'status' => $i <= $ranked ? 'ranked' : 'insufficient', 'score' => $i <= $ranked ? 91 - $i : null, 'category_rank' => $i <= $ranked ? $i : null,
            'overall_rank' => $i <= $ranked ? $i : null])->save();
    }
    app()->forgetInstance(IndexBrowser::class);

    return $run;
}

it('shows at most 10 ranked projects per category, with "Top 10" only when ten qualify', function () {
    t10Run(12, 2);

    $page = $this->get(route('rankings.category', 'housing-societies'))->assertOk()->assertSee('Top 10 Verified Housing Societies in Pakistan');
    $html = $page->getContent();
    foreach (range(1, 10) as $i) {
        expect($html)->toContain(sprintf('Top Ten Society %02d', $i));
    }
    // Ranks 11 and 12 are listed below, without a rank or score; never in the leaderboard table.
    $leaderboard = substr($html, strpos($html, '<table'), strpos($html, '</table>') - strpos($html, '<table'));
    expect($leaderboard)->not->toContain('Top Ten Society 11')->not->toContain('Top Ten Society 12')
        ->and(substr_count($leaderboard, '<tr>') - 1)->toBe(10);
    expect(app(IndexBrowser::class)->leaderboard('housing-societies', null, 100))->toHaveCount(10);
});

it('never lets a city filter reveal a position below the published top 10', function () {
    t10Run(14);

    $rows = app(IndexBrowser::class)->leaderboard('housing-societies', 'Karachi', 100);
    expect($rows->max('category_rank'))->toBeLessThanOrEqual(10)->and($rows->pluck('category_rank')->all())->toBe([2, 4, 6, 8, 10]);
    // Lower-ranked projects may be listed below the table, but never inside the leaderboard itself.
    $html = $this->get(route('rankings.city-category', ['karachi', 'housing-societies']))->assertOk()->getContent();
    $table = substr($html, strpos($html, '<table'), strpos($html, '</table>') - strpos($html, '<table'));
    expect($table)->toContain('Top Ten Society 10')->not->toContain('Top Ten Society 12')->not->toContain('Top Ten Society 14');
});

it('uses "Verified … Rankings" when fewer than ten qualify, and the plain name when none do', function () {
    t10Run(3);

    expect(IndexBrowser::leaderboardTitle('housing-societies', 3))->toBe('Verified Housing Society Rankings')
        ->and(IndexBrowser::leaderboardTitle('housing-societies', 10))->toBe('Top 10 Verified Housing Societies')
        ->and(IndexBrowser::leaderboardTitle('shopping-malls', 0))->toBe('Shopping Malls');
    $this->get(route('rankings.category', 'housing-societies'))->assertOk()->assertSee('Verified Housing Society Rankings in Pakistan')->assertDontSee('Top 10');
    $this->get(route('rankings.index'))->assertOk()->assertSee('Verified Housing Society Rankings')->assertDontSee('Top 10 Verified');
});

it('shows a project ranked below the top 10 as ranked, without its position', function () {
    t10Run(12);
    $eleventh = Project::where('name', 'Top Ten Society 11')->firstOrFail();

    $this->get(route('rankings.project', $eleventh))->assertOk()->assertSee('Ranked, outside the published top 10')->assertDontSee('#11');
    expect(IndexBrowser::publicRank(InvestmentIndexScore::where('project_id', $eleventh->id)->first()))->toBeNull();
});

it('limits the Overall Index to the top 10 too', function () {
    t10Run(12);

    expect(app(IndexBrowser::class)->overall(100))->toHaveCount(10);
});

it('records a dealer submission as its own publication basis, and blocks publishing without one', function () {
    $listing = Property::factory()->create();
    expect($listing->publication_basis)->toBe('direct_submission');

    $imported = Property::factory()->create(['status' => PropertyStatus::Pending]);
    $imported->forceFill(['publication_basis' => null, 'source_url' => 'https://partner.example/1'])->save();
    expect(fn () => $imported->forceFill(['status' => PropertyStatus::Published])->save())->toThrow(DomainException::class);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->patch(route('admin.properties.moderate', $imported->fresh()), ['decision' => 'approve'])->assertStatus(422);
    expect($imported->fresh()->status)->toBe(PropertyStatus::Pending);
});

function t10Csv(array $rows): string
{
    $file = tempnam(sys_get_temp_dir(), 'imp').'.csv';
    $handle = fopen($file, 'w');
    fputcsv($handle, ['source_reference', 'source_url', 'title', 'purpose', 'type', 'city', 'area', 'area_size', 'area_unit', 'price', 'bedrooms', 'description', 'amenities']);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return $file;
}

it('imports authorised listings into the moderation queue only, skipping duplicates and bad rows', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create(['email' => 'agency@example.com']);
    Area::create(['city' => 'Lahore', 'name' => 'Import Gardens', 'slug' => 'import-gardens', 'is_active' => true]);
    $good = ['A-1', 'https://agency.example/a-1', '10 Marla House in Import Gardens', 'sale', 'house', 'Lahore', 'Import Gardens', '10', 'marla', '45000000', '4', str_repeat('A well kept family house near schools and the main boulevard. ', 2), 'parking;gas'];
    $file = t10Csv([
        $good,
        ['A-1', 'https://agency.example/a-1-copy', ...array_slice($good, 2)],
        ['A-2', 'https://agency.example/a-2', '5 Marla House With Placeholder', 'sale', 'house', 'Lahore', 'Import Gardens', '5', 'marla', '20000000', '3', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor.', ''],
        ['A-3', 'https://agency.example/a-3', '8 Marla House in Nowhere Town', 'sale', 'house', 'Lahore', 'Nowhere Town', '8', 'marla', '30000000', '3', str_repeat('A complete and accurate description of this house. ', 2), ''],
        ['', 'https://agency.example/a-4', '8 Marla House Without Reference', 'sale', 'house', 'Lahore', 'Import Gardens', '8', 'marla', '30000000', '3', str_repeat('A complete and accurate description of this house. ', 2), ''],
    ]);
    $options = ['file' => $file, '--lister' => 'agency@example.com', '--basis' => 'authorized_feed', '--source' => 'Example Agency'];

    Artisan::call('meezan:listings-import', $options);
    expect(Property::count())->toBe(0)->and(Artisan::output())->toContain('Dry run');

    Artisan::call('meezan:listings-import', $options + ['--commit' => true]);
    $imported = Property::sole();
    expect($imported->status)->toBe(PropertyStatus::Pending)->and($imported->publication_basis)->toBe('authorized_feed')
        ->and($imported->source_name)->toBe('Example Agency')->and($imported->source_reference)->toBe('A-1')->and($imported->user_id)->toBe($dealer->id)
        ->and($imported->images()->count())->toBe(0)->and($imported->is_demo)->toBeFalse();
    expect(Artisan::output())->toContain('imported: 1')->toContain('duplicate: 1')->toContain('invalid: 3');
    $this->get(route('properties.index'))->assertDontSee('10 Marla House in Import Gardens');

    // Running the same file again imports nothing new.
    Artisan::call('meezan:listings-import', $options + ['--commit' => true]);
    expect(Property::count())->toBe(1);
    @unlink($file);
});

it('refuses an import without an authorised basis, a named source or a real dealer', function () {
    User::factory()->dealer(DealerStatus::Verified)->create(['email' => 'agency@example.com']);
    $sample = User::factory()->dealer(DealerStatus::Verified)->create(['email' => 'sample@example.com']);
    $sample->dealerProfile->forceFill(['is_demo' => true])->save();
    $file = t10Csv([]);

    expect(Artisan::call('meezan:listings-import', ['file' => $file, '--lister' => 'agency@example.com', '--basis' => 'portal_advert', '--source' => 'Zameen']))->toBe(1)
        ->and(Artisan::call('meezan:listings-import', ['file' => $file, '--lister' => 'agency@example.com', '--basis' => 'licence']))->toBe(1)
        ->and(Artisan::call('meezan:listings-import', ['file' => $file, '--lister' => 'sample@example.com', '--basis' => 'licence', '--source' => 'X']))->toBe(1);
    @unlink($file);
    Cache::flush();
});

it('treats accounts on reserved test domains as samples from the start', function () {
    expect(SampleContent::isTestEmail('qa.dealer@meezanproperty.test'))->toBeTrue()
        ->and(SampleContent::isTestEmail('a@agency.example'))->toBeTrue()
        ->and(SampleContent::isTestEmail('owner@gmail.com'))->toBeFalse()
        ->and(SampleContent::isTestEmail('owner@example.com'))->toBeFalse()
        ->and(SampleContent::isTestEmail('owner@contest.com.pk'))->toBeFalse();

    $qa = User::factory()->dealer(DealerStatus::Verified)->create(['email' => 'qa.dealer@meezanproperty.test']);
    $listing = Property::factory()->forDealer($qa)->create(['title' => 'QA House Should Stay Hidden']);

    expect($qa->dealerProfile->is_demo)->toBeTrue()->and($qa->dealerProfile->isVerified())->toBeFalse()
        ->and($listing->is_demo)->toBeTrue()->and(Property::published()->count())->toBe(0);
    $this->get(route('properties.index'))->assertDontSee('QA House Should Stay Hidden');
    $this->get(route('dealers.index'))->assertDontSee($qa->dealerProfile->agency_name);
});

it('finds and flags existing test content only when told to commit', function () {
    $qa = User::factory()->dealer(DealerStatus::Verified)->create();
    $listing = Property::factory()->forDealer($qa)->create(['title' => 'Old QA Listing For Flagging']);
    $qa->forceFill(['email' => 'qa@site.test'])->save(); // records made before the account was known to be a test account
    $placeholder = Property::factory()->create(['title' => 'Placeholder Text Listing']);
    $placeholder->forceFill(['description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor.'])->saveQuietly();
    $real = Property::factory()->create(['title' => 'A Real Family House']);

    Artisan::call('meezan:flag-sample-content');
    expect($listing->fresh()->is_demo)->toBeFalse()->and(Artisan::output())->toContain('Dry run');

    Artisan::call('meezan:flag-sample-content', ['--commit' => true]);
    expect($listing->fresh()->is_demo)->toBeTrue()->and($listing->fresh()->status)->toBe(PropertyStatus::Archived)
        ->and($placeholder->fresh()->is_demo)->toBeTrue()->and($qa->dealerProfile->fresh()->is_demo)->toBeTrue()
        ->and($real->fresh()->is_demo)->toBeFalse()->and($real->fresh()->status)->toBe(PropertyStatus::Published);
    $this->assertDatabaseHas('audit_logs', ['action' => 'property.marked_sample', 'auditable_id' => $listing->id]);
});
