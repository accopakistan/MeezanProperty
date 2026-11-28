<?php

use App\Enums\DealerStatus;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/*
| Indexing rules: what the sitemap lists must match what each page allows (robots meta),
| and pages kept out of the index still let crawlers follow their links.
*/

function seoProject(string $name, array $attributes = [], bool $demo = false): Project
{
    $p = new Project(array_merge(['name' => $name, 'category' => 'residential_society', 'city' => 'Lahore', 'status' => 'operational',
        'description' => $name.' is a development in Lahore used by the automated tests.'], $attributes));
    $p->forceFill(['is_published' => true, 'is_demo' => $demo, 'research_facts' => $attributes['research_facts'] ?? null])->save();

    return $p;
}

beforeEach(fn () => Cache::flush());

it('indexes project profiles with researched content and keeps name-only profiles out of the index and sitemap', function () {
    $researched = seoProject('Seo Researched Town', ['research_facts' => [['label' => 'Approved plots', 'value' => '393 residential', 'source' => 'LDA', 'url' => 'https://lda.gop.pk/approved-schemes']]]);
    $developer = seoProject('Seo Developer Heights', ['developer' => 'Seo Builders']);
    $approved = seoProject('Seo Approved Enclave', ['approval_status' => 'approved']);
    $thin = seoProject('Seo Name Only Mall');
    $demo = seoProject('Seo Demo City', ['developer' => 'Demo Builders'], demo: true);

    foreach ([$researched, $developer, $approved] as $p) {
        $this->get(route('projects.show', $p))->assertOk()
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee('<link rel="canonical" href="'.route('projects.show', $p).'">', false);
    }
    $this->get(route('projects.show', $thin))->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false)->assertDontSee('rel="canonical"', false);

    $xml = $this->get(route('sitemap.show', 'projects-1'))->assertOk()->getContent();
    expect($xml)->toContain(route('projects.show', $researched))->toContain(route('projects.show', $developer))->toContain(route('projects.show', $approved))
        ->not->toContain(route('projects.show', $thin))->not->toContain($demo->slug)
        ->and(substr_count($xml, '<lastmod>'))->toBe(3);
    $this->get('/sitemaps/projects-2.xml')->assertNotFound();
});

it('lists public dealer profiles in the agents sitemap, and not sample, suspended or empty ones', function () {
    $verified = User::factory()->dealer(DealerStatus::Verified)->create()->dealerProfile;
    $withListing = User::factory()->dealer()->create();
    Property::factory()->forDealer($withListing)->create();
    $empty = User::factory()->dealer()->create()->dealerProfile;
    $suspended = User::factory()->dealer(DealerStatus::Suspended)->create()->dealerProfile;
    $sample = User::factory()->dealer(DealerStatus::Verified)->create()->dealerProfile;
    $sample->forceFill(['is_demo' => true])->save();

    $xml = $this->get(route('sitemap.show', 'agents'))->assertOk()->getContent();
    expect($xml)->toContain(route('dealers.show', $verified))->toContain(route('dealers.show', $withListing->dealerProfile))
        ->not->toContain(route('dealers.show', $empty))->not->toContain(route('dealers.show', $suspended))->not->toContain(route('dealers.show', $sample));
    $this->get(route('dealers.show', $verified))->assertOk()->assertSee('<link rel="canonical" href="'.route('dealers.show', $verified).'">', false);
});

it('lists investment research pages that are indexable, and never a noindex page', function () {
    $xml = $this->get(route('sitemap.show', 'research'))->assertOk()->getContent();

    expect($xml)->toContain(route('rankings.index'))->toContain(route('rankings.methodology'))
        ->toContain(route('investment.methodology'))->toContain(route('investment.yield'))
        ->not->toContain(route('rankings.overall'));
    $this->get(route('rankings.overall'))->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    $this->get(route('investment.methodology'))->assertOk()->assertSee('<meta name="robots" content="index, follow', false);
});

it('never lists the same URL twice across the sitemap files', function () {
    Property::factory()->count(2)->create();
    seoProject('Seo Unique Town', ['developer' => 'Unique Builders']);

    $index = $this->get('/sitemap.xml')->assertOk()->getContent();
    preg_match_all('#<loc>([^<]+)</loc>#', $index, $files);
    $all = collect($files[1])->flatMap(function ($file) {
        preg_match_all('#<loc>([^<]+)</loc>#', $this->get(parse_url($file, PHP_URL_PATH))->assertOk()->getContent(), $urls);

        return $urls[1];
    });

    expect($all)->not->toBeEmpty()->and($all->duplicates())->toBeEmpty();
});

it('labels approval sources as official only for the authority\'s own website', function () {
    expect(\App\Support\Faq::sourceKind('https://www.cda.gov.pk/public/housing-schemes'))->toBe('official')
        ->and(\App\Support\Faq::sourceKind('https://mda.punjab.gov.pk/system/files/approved.pdf'))->toBe('official')
        ->and(\App\Support\Faq::sourceKind('https://qda.gob.pk/img/scheme_lists/approved_scheme.pdf'))->toBe('official')
        ->and(\App\Support\Faq::sourceKind('https://www.zameen.com/news/list-of-lda-approved-housing-schemes-in-lahore-district.html'))->toBe('secondary')
        ->and(\App\Support\Faq::sourceKind('https://gov.pk.example.com/list'))->toBe('secondary')
        ->and(\App\Support\Faq::sourceKind(null))->toBe('none');
});
