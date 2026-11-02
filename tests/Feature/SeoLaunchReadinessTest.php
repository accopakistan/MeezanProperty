<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Area;
use App\Models\Property;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/*
| Phase 12: SEO meta, location category pages, sitemaps, robots, indexing
| rules, query stability and launch security hardening.
*/

/** JSON-LD blocks on a page, decoded. */
function jsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);

    return array_map(fn ($json) => json_decode($json, true), $m[1]);
}

function houses(int $n, array $attributes = []): void
{
    Property::factory()->count($n)->create($attributes + ['city' => 'Lahore', 'area_id' => area('Gulberg')->id]);
}

// ── Meta tags ────────────────────────────────────────────────────────────

it('gives public pages robots, canonical, Open Graph and Twitter tags', function () {
    $this->get('/')->assertOk()
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
        ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
        ->assertSee('<meta property="og:site_name" content="Meezan Property">', false)
        ->assertSee('<meta property="og:image"', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('"@type":"Organization"', false);
});

it('keeps private pages out of search with noindex, nofollow and no canonical', function () {
    $this->get('/login')->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('rel="canonical"', false)
        ->assertDontSee('og:title', false);

    $this->actingAs(User::factory()->create())->get('/account')->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

// ── Property pages ───────────────────────────────────────────────────────

it('builds the property title and description from the real listing', function () {
    $property = Property::factory()->create([
        'title' => 'Corner House', 'area_size' => 10, 'bedrooms' => 5, 'bathrooms' => 6,
        'city' => 'Lahore', 'area_id' => area('DHA Phase 6')->id, 'price' => 95_000_000,
    ]);

    expect(Seo::propertyTitle($property))->toBe('10 Marla House for Sale in DHA Phase 6, Lahore')
        ->and(mb_strlen(Seo::propertyDescription($property)))->toBeLessThanOrEqual(155)
        ->and(Seo::propertyDescription($property))->toContain('5 bed');

    $this->get(route('properties.show', $property))->assertOk()
        ->assertSee('<title>10 Marla House for Sale in DHA Phase 6, Lahore', false)
        ->assertSee('<link rel="canonical" href="'.route('properties.show', $property).'">', false)
        ->assertSee('<meta property="og:title" content="10 Marla House for Sale in DHA Phase 6, Lahore', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});

it('outputs listing schema without exact coordinates unless the dealer chose to show them', function () {
    $approximate = Property::factory()->create();
    $approximate->forceFill(['latitude' => 31.5, 'longitude' => 74.3, 'location_privacy' => 'approximate'])->save();
    $exact = Property::factory()->create();
    $exact->forceFill(['latitude' => 31.5204, 'longitude' => 74.3587, 'location_privacy' => 'exact'])->save();

    $schema = collect(jsonLd($this->get(route('properties.show', $approximate))->getContent()))->firstWhere('@type', 'RealEstateListing');
    expect($schema)->not->toBeNull()
        ->and($schema['offers']['priceCurrency'])->toBe('PKR')
        ->and($schema['contentLocation']['address']['addressCountry'])->toBe('PK')
        ->and($schema['contentLocation'])->not->toHaveKey('geo')
        ->and(json_encode($schema))->not->toContain('31.5');

    $exactSchema = collect(jsonLd($this->get(route('properties.show', $exact))->getContent()))->firstWhere('@type', 'RealEstateListing');
    expect($exactSchema['contentLocation']['geo']['@type'])->toBe('GeoCoordinates');
});

it('lets search engines follow but not index demo listings', function () {
    $demo = Property::factory()->create();
    $demo->forceFill(['is_demo' => true])->save();

    $html = $this->get(route('properties.show', $demo))->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->getContent();

    expect(collect(jsonLd($html))->firstWhere('@type', 'RealEstateListing'))->toBeNull();
});

it('suggests related listings from the same city, purpose and type', function () {
    $property = Property::factory()->create(['city' => 'Lahore', 'title' => 'Main House']);
    Property::factory()->create(['city' => 'Lahore', 'title' => 'Neighbour House']);
    Property::factory()->create(['city' => 'Karachi', 'area_id' => null, 'title' => 'Karachi House']);
    Property::factory()->create(['city' => 'Lahore', 'purpose' => Purpose::Rent, 'title' => 'Rental House']);

    $this->get(route('properties.show', $property))->assertOk()
        ->assertSee('Neighbour House')
        ->assertDontSee('Karachi House')
        ->assertDontSee('Rental House')
        ->assertSee(route('cities.category', ['lahore', 'houses-for-sale']));
});

// ── City and area category pages ─────────────────────────────────────────

it('renders a city category page with only matching live listings', function () {
    houses(3, ['title' => 'Real Gulberg House']);
    Property::factory()->create(['city' => 'Lahore', 'type' => PropertyType::ResidentialPlot, 'bedrooms' => null, 'bathrooms' => null, 'title' => 'A Residential Plot']);
    Property::factory()->status(PropertyStatus::Pending)->create(['city' => 'Lahore', 'title' => 'Pending House']);

    $this->get('/lahore/houses-for-sale')->assertOk()
        ->assertSee('Houses for Sale in Lahore')
        ->assertSee('Real Gulberg House')
        ->assertDontSee('A Residential Plot')
        ->assertDontSee('Pending House')
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
        ->assertSee('<link rel="canonical" href="'.route('cities.category', ['lahore', 'houses-for-sale']).'">', false)
        ->assertSee('"@type":"CollectionPage"', false);
});

it('keeps thin category pages usable but out of the index', function () {
    houses(2);
    $demo = Property::factory()->create(['city' => 'Lahore']);
    $demo->forceFill(['is_demo' => true])->save();

    // Two real listings and one demo: under the three-listing threshold.
    $this->get('/lahore/houses-for-sale')->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertDontSee('rel="canonical"', false)
        ->assertDontSee('"@type":"CollectionPage"', false);

    $this->get('/karachi/flats-for-rent')->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('404s unknown categories, cities and missing pages', function () {
    $this->get('/lahore/castles-for-sale')->assertNotFound();
    $this->get('/sialkot/houses-for-sale')->assertNotFound();
    $this->get('/areas/'.area('Gulberg')->slug.'/castles-for-sale')->assertNotFound();
    $this->get('/lahore/houses-for-sale?page=5')->assertNotFound();
});

it('canonicalises paginated category pages to themselves', function () {
    houses(30);

    $this->get('/lahore/houses-for-sale?page=2')->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('cities.category', ['lahore', 'houses-for-sale']).'?page=2">', false);
});

it('renders area category pages and redirects merged areas', function () {
    $gulberg = area('Gulberg');
    houses(3, ['title' => 'Gulberg Family House']);

    $this->get(route('areas.category', [$gulberg->slug, 'houses-for-sale']))->assertOk()
        ->assertSee('Houses for Sale in Gulberg, Lahore')
        ->assertSee('Gulberg Family House');

    $old = Area::create(['city' => 'Lahore', 'name' => 'Old Gulberg Name', 'slug' => 'old-gulberg-name', 'merged_into_id' => $gulberg->id]);
    $this->get(route('areas.category', [$old->slug, 'houses-for-sale']))
        ->assertRedirect(route('areas.category', [$gulberg->slug, 'houses-for-sale']))
        ->assertStatus(301);

    $inactive = Area::create(['city' => 'Lahore', 'name' => 'Closed Scheme', 'slug' => 'closed-scheme', 'is_active' => false]);
    $this->get(route('areas.category', [$inactive->slug, 'houses-for-sale']))->assertNotFound();
});

it('links city pages to their category pages with real counts', function () {
    houses(4);

    $this->get('/lahore')->assertOk()
        ->assertSee(route('cities.category', ['lahore', 'houses-for-sale']))
        ->assertSee(route('cities.category', ['lahore', 'property-for-sale']));
});

// ── Search, map and indexing rules ───────────────────────────────────────

it('canonicalises search to its defining filters and noindexes refinements', function () {
    houses(3);

    $this->get('/properties?city=Lahore&purpose=sale&sort=price_asc')->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false);

    $this->get('/properties?city=Lahore&purpose=sale')->assertOk()
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
        ->assertSee('rel="canonical" href="'.e(route('properties.index', ['city' => 'Lahore', 'purpose' => 'sale'])).'"', false);

    $this->get('/properties?city=Lahore&min_price=1000&max_price=2000')->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('keeps the map page out of the index', function () {
    $this->get('/map')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
});

// ── Sitemaps and robots.txt ──────────────────────────────────────────────

it('lists indexable category pages in the sitemap and leaves out thin ones', function () {
    houses(3);

    $this->get('/sitemap.xml')->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertSee(route('cities.show', 'karachi'))
        ->assertSee(route('cities.category', ['lahore', 'houses-for-sale']))
        ->assertSee(route('areas.category', [area('Gulberg')->slug, 'houses-for-sale']))
        ->assertDontSee(route('cities.category', ['lahore', 'plots-for-sale']))
        ->assertDontSee('/dealer/')
        ->assertDontSee('/admin')
        ->assertDontSee('/account');
});

it('serves the split sitemap files and an index once listings outgrow one file', function () {
    houses(3);

    $this->get('/sitemaps/pages.xml')->assertOk()->assertSee(route('pricing'));
    $this->get('/sitemaps/locations.xml')->assertOk()->assertSee(route('cities.category', ['lahore', 'houses-for-sale']));
    $this->get('/sitemaps/properties-1.xml')->assertOk()->assertSee(route('properties.show', Property::first()));
    $this->get('/sitemaps/properties-9.xml')->assertNotFound();
    $this->get('/sitemaps/secrets.xml')->assertNotFound();
});

it('publishes robots.txt that blocks private areas and points at the sitemap', function () {
    foreach ([public_path('robots.txt'), base_path('robots.txt')] as $file) {
        $robots = file_get_contents($file);
        expect($robots)->toContain('Disallow: /admin')
            ->toContain('Disallow: /dealer/')
            ->toContain('Disallow: /account')
            ->toContain('Sitemap: https://meezanproperty.com/sitemap.xml')
            ->not->toContain('Disallow: /properties$');
    }
});

// ── Performance ──────────────────────────────────────────────────────────

it('runs the same number of queries on location pages as listings grow', function () {
    $count = function (string $url): int {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $this->get($url)->assertOk();
        app('events')->forget(QueryExecuted::class);

        return $queries;
    };
    $gulberg = area('Gulberg');
    $urls = ['/lahore', '/lahore/houses-for-sale', '/areas/'.$gulberg->slug.'/houses-for-sale', '/areas/'.$gulberg->slug];

    houses(3);
    $small = collect($urls)->mapWithKeys(fn ($u) => [$u => $count($u)]);
    cache()->flush();
    houses(15);
    $large = collect($urls)->mapWithKeys(fn ($u) => [$u => $count($u)]);

    foreach ($small as $url => $queries) {
        expect($large[$url])->toBeLessThanOrEqual($queries, "{$url} runs more queries as listings grow")
            ->and($large[$url])->toBeLessThanOrEqual(25, "{$url} exceeded its query budget");
    }
});

// ── Security hardening ───────────────────────────────────────────────────

it('rate limits password reset submissions', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->post('/reset-password', ['token' => 'x', 'email' => 'someone@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])
            ->assertStatus(302);
    }

    $this->post('/reset-password', ['token' => 'x', 'email' => 'someone@example.com', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])
        ->assertStatus(429);
});

it('hides a staging copy of the site from search engines only when asked', function () {
    $this->get('/')->assertHeaderMissing('X-Robots-Tag');

    config(['meezan.seo.noindex_site' => true]);
    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('keeps the root .htaccess rule that refuses internal files', function () {
    $htaccess = file_get_contents(base_path('.htaccess'));

    expect($htaccess)->toContain('RewriteRule !^(index\.php|robots\.txt|favicon\.(ico|svg)|public/.*)$ - [F,L]')
        ->and(strpos($htaccess, '[F,L]'))->toBeLessThan(strpos($htaccess, 'RewriteRule ^ index.php [L]'));
});

it('still refuses dashboards to the wrong roles', function () {
    $buyer = User::factory()->create();
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();

    $this->get('/dealer/dashboard')->assertRedirect('/login');
    $this->actingAs($buyer)->get('/dealer/dashboard')->assertForbidden();
    $this->actingAs($dealer)->get('/admin')->assertForbidden();
});
