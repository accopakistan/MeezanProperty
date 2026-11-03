<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\AreaBoundary;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use App\Support\BoundaryGeometry;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
| Phase 13: MapLibre map search, map data endpoint, coordinate privacy,
| society / area boundaries and the admin boundary workflow.
*/

/** A closed square [lng, lat] ring around a point in Lahore. */
function square(float $lat = 31.52, float $lng = 74.35, float $half = 0.01): array
{
    return ['type' => 'Polygon', 'coordinates' => [[
        [$lng - $half, $lat - $half], [$lng + $half, $lat - $half], [$lng + $half, $lat + $half], [$lng - $half, $lat + $half], [$lng - $half, $lat - $half],
    ]]];
}

function boundaryFor(string $areaName, string $status, array $geometry = []): AreaBoundary
{
    $boundary = new AreaBoundary(['source' => 'admin_drawn']);
    $boundary->area()->associate(area($areaName));
    $boundary->setGeometry(BoundaryGeometry::parse($geometry ?: square()))->forceFill(['status' => $status])->save();

    return $boundary;
}

function mapData($test, array $query = []): array
{
    return $test->getJson(route('properties.map.data', $query))->assertOk()->json();
}

// ── Map page ─────────────────────────────────────────────────────────────

it('renders the MapLibre map page with its configuration, cities and noindex', function () {
    $html = $this->get(route('properties.map', ['city' => 'Lahore']))->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('data-geo-map=', false)
        ->assertSee('Search this area')
        ->getContent();

    preg_match('/data-geo-map="([^"]*)"/', $html, $m);
    $config = json_decode(html_entity_decode($m[1]), true);

    expect($config['style'])->toBe(config('meezan.map.style_url'))
        ->and($config['raster']['tiles'])->toBe(config('meezan.map.tile_url'))
        ->and($config['urls']['data'])->toBe(route('properties.map.data'))
        // Centres come from the cities table (seven rows in a real install) or config; never invented.
        ->and($config['cities'])->toHaveKey('Lahore')
        ->and($config['center'])->toHaveCount(2);
    foreach (config('meezan.cities') as $city) {
        expect($html)->toContain('<option value="'.$city.'"');
    }
});

it('allows the map style, tiles and worker in the Content-Security-Policy', function () {
    $csp = $this->get(route('properties.map'))->headers->get('Content-Security-Policy');

    expect($csp)->toContain('connect-src \'self\' https://tile.openstreetmap.org https://tiles.openfreemap.org')
        ->toContain("worker-src 'self' blob:")
        ->not->toContain('unsafe-eval');
});

// ── Map data endpoint ────────────────────────────────────────────────────

it('returns markers, counts and the rendered list for real published listings only', function () {
    Property::factory()->create(['title' => 'Pinned Live House', 'latitude' => 31.52, 'longitude' => 74.35]);
    Property::factory()->status(PropertyStatus::Pending)->create(['title' => 'Pending Pinned', 'latitude' => 31.53, 'longitude' => 74.36]);
    Property::factory()->create(['title' => 'Expired Pinned', 'latitude' => 31.54, 'longitude' => 74.37, 'expires_at' => now()->subDay()]);

    $response = $this->getJson(route('properties.map.data'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    $data = $response->json();

    expect($data['total'])->toBe(1)
        ->and($data['mode'])->toBe('points')
        ->and($data['located'])->toBe(1)
        ->and(collect($data['markers'])->pluck('title')->all())->toBe(['Pinned Live House'])
        ->and($data['list'])->toContain('Pinned Live House')->toContain('1 Property Found')->not->toContain('Pending Pinned')
        ->and($data['summary'])->toContain('1 on the map');
});

it('applies the existing filters to markers, list and counts together', function () {
    Property::factory()->create(['title' => 'Sale House Lahore', 'latitude' => 31.52, 'longitude' => 74.35, 'price' => 30_000_000]);
    Property::factory()->create(['title' => 'Rent Flat Lahore', 'purpose' => Purpose::Rent, 'type' => PropertyType::Apartment, 'latitude' => 31.51, 'longitude' => 74.34, 'price' => 90_000]);
    Property::factory()->create(['title' => 'Karachi House', 'city' => 'Karachi', 'area_id' => null, 'latitude' => 24.86, 'longitude' => 67.01, 'price' => 50_000_000]);
    Property::factory()->create(['title' => 'Pricey House', 'latitude' => 31.50, 'longitude' => 74.33, 'price' => 90_000_000]);

    $titles = fn (array $q) => collect(mapData($this, $q)['markers'])->pluck('title')->sort()->values()->all();

    expect($titles(['purpose' => 'rent']))->toBe(['Rent Flat Lahore'])
        ->and($titles(['type' => 'apartment']))->toBe(['Rent Flat Lahore'])
        ->and($titles(['city' => 'Karachi']))->toBe(['Karachi House'])
        ->and($titles(['city' => 'Lahore', 'purpose' => 'sale', 'max_price' => 40_000_000]))->toBe(['Sale House Lahore'])
        ->and(mapData($this, ['city' => 'Lahore'])['total'])->toBe(3);
});

it('filters by area including its sub-areas', function () {
    $phase = area('DHA Phase 6');
    Property::factory()->create(['title' => 'In DHA Phase 6', 'area_id' => $phase->id, 'latitude' => 31.47, 'longitude' => 74.45]);
    Property::factory()->create(['title' => 'In Gulberg', 'area_id' => area('Gulberg')->id, 'latitude' => 31.52, 'longitude' => 74.35]);

    $data = mapData($this, ['area' => $phase->slug]);

    expect($data['total'])->toBe(1)->and($data['markers'][0]['title'])->toBe('In DHA Phase 6');
});

it('searches the visible map area, with area-centre listings only when asked', function () {
    area('Gulberg')->update(['latitude' => 31.512, 'longitude' => 74.343]);
    area('Model Town')->update(['latitude' => 31.30, 'longitude' => 74.10]);
    Property::factory()->create(['title' => 'Pinned Inside', 'latitude' => 31.52, 'longitude' => 74.35]);
    Property::factory()->create(['title' => 'Pinned Outside', 'latitude' => 31.30, 'longitude' => 74.10]);
    Property::factory()->create(['title' => 'Gulberg No Pin', 'area_id' => area('Gulberg')->id, 'latitude' => null, 'longitude' => null]);
    Property::factory()->create(['title' => 'Model Town No Pin', 'area_id' => area('Model Town')->id, 'latitude' => null, 'longitude' => null]);
    $box = ['north' => 31.6, 'south' => 31.4, 'east' => 74.5, 'west' => 74.2];

    $strict = mapData($this, $box);
    expect($strict['total'])->toBe(1)->and($strict['list'])->toContain('Pinned Inside')->not->toContain('Gulberg No Pin');

    $withCentres = mapData($this, $box + ['centres' => 1]);
    expect($withCentres['total'])->toBe(2)
        ->and($withCentres['list'])->toContain('Pinned Inside')->toContain('Gulberg No Pin')->not->toContain('Pinned Outside')->not->toContain('Model Town No Pin')
        ->and(collect($withCentres['markers'])->pluck('kind')->sort()->values()->all())->toBe(['area', 'pin']);

    // Bad bounds are ignored rather than trusted.
    expect(mapData($this, ['north' => 'x', 'south' => 31.4, 'east' => 74.5, 'west' => 74.2])['total'])->toBe(4);
});

it('switches to counted grid cells when too many located listings match', function () {
    config(['meezan.map.max_points' => 3]);
    Property::factory()->count(5)->sequence(fn ($s) => ['latitude' => 31.50 + $s->index * 0.001, 'longitude' => 74.30 + $s->index * 0.001])->create();
    Property::factory()->create(['latitude' => 31.70, 'longitude' => 74.60]);

    $data = mapData($this);

    expect($data['mode'])->toBe('grid')
        ->and($data['located'])->toBe(6)
        ->and(collect($data['markers'])->sum('count'))->toBe(6)
        ->and(collect($data['markers'])->pluck('kind')->unique()->all())->toBe(['grid'])
        ->and(json_encode($data['markers']))->not->toContain('"title"');
});

it('keeps marker data minimal: no contact details, documents or dealer notes', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create(['phone' => '03001234567']);
    $dealer->dealerProfile->forceFill(['public_phone' => '03007654321', 'whatsapp_number' => '923001112222'])->save();
    Property::factory()->forDealer($dealer)->create(['latitude' => 31.52, 'longitude' => 74.35]);

    $markers = json_encode(mapData($this)['markers']);

    expect($markers)->not->toContain('0300')->not->toContain('92300')->not->toContain('@')
        ->and(array_keys(mapData($this)['markers'][0]))->not->toContain('description');
});

it('does not grow its query count with more listings', function () {
    $count = function (): int {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $this->getJson(route('properties.map.data', ['city' => 'Lahore']))->assertOk();
        app('events')->forget(QueryExecuted::class);

        return $queries;
    };
    $seed = fn (int $n) => Property::factory()->count($n)->sequence(fn ($s) => ['latitude' => 31.4 + $s->index * 0.002, 'longitude' => 74.3])->create()
        ->each(fn ($p) => $p->images()->create(['path' => 'x.webp', 'sort_order' => 1]));

    $seed(3);
    $small = $count();
    $seed(20);
    expect($count())->toBeLessThanOrEqual($small)->toBeLessThanOrEqual(30);
});

// ── Coordinate privacy ───────────────────────────────────────────────────

it('never sends a private exact pin to the map', function () {
    $approximate = Property::factory()->create(['title' => 'Approx Home', 'area_id' => area('Gulberg')->id]);
    $approximate->forceFill(['latitude' => 31.5204321, 'longitude' => 74.3587654, 'location_privacy' => 'approximate'])->save();
    $areaOnly = Property::factory()->create(['title' => 'Area Only Home', 'area_id' => area('Gulberg')->id]);
    $areaOnly->forceFill(['latitude' => 31.4987654, 'longitude' => 74.3012345, 'location_privacy' => 'area'])->save();
    area('Gulberg')->update(['latitude' => 31.512, 'longitude' => 74.343]);

    $json = $this->getJson(route('properties.map.data'))->getContent();
    $page = $this->get(route('properties.map'))->getContent();

    foreach (['31.5204321', '74.3587654', '31.4987654', '74.3012345'] as $secret) {
        expect($json)->not->toContain($secret)->and($page)->not->toContain($secret);
    }
    $markers = collect(json_decode($json, true)['markers']);
    expect($markers->firstWhere('kind', 'approx')['title'])->toBe('Approx Home')
        ->and($markers->firstWhere('kind', 'area')['title'])->toBe('Gulberg');
});

it('serves the detail panel only for public listings, without contact numbers', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $dealer->dealerProfile->forceFill(['public_phone' => '03007654321'])->save();
    $live = Property::factory()->forDealer($dealer)->create(['title' => 'Panel House']);
    $draft = Property::factory()->status(PropertyStatus::Draft)->create();

    $this->get(route('properties.map.preview', $live))->assertOk()->assertSee('Panel House')->assertDontSee('03007654321');
    $this->get(route('properties.map.preview', $draft))->assertNotFound();
});

// ── Boundary geometry ────────────────────────────────────────────────────

it('accepts GeoJSON polygons, features and single-feature collections', function () {
    $polygon = square();

    $parsed = BoundaryGeometry::parse(json_encode($polygon));
    expect($parsed->geometry['type'])->toBe('Polygon')
        ->and($parsed->vertexCount())->toBe(4)
        ->and($parsed->bounds())->toBe(['min_lat' => 31.51, 'max_lat' => 31.53, 'min_lng' => 74.34, 'max_lng' => 74.36])
        ->and($parsed->areaSqKm())->toBeGreaterThan(4)->toBeLessThan(5)
        ->and($parsed->contains(31.52, 74.35))->toBeTrue()
        ->and($parsed->contains(31.60, 74.35))->toBeFalse();

    expect(BoundaryGeometry::parse(['type' => 'Feature', 'properties' => [], 'geometry' => $polygon])->geometry['type'])->toBe('Polygon')
        ->and(BoundaryGeometry::parse(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature', 'geometry' => $polygon]]])->vertexCount())->toBe(4)
        ->and(BoundaryGeometry::parse(['type' => 'MultiPolygon', 'coordinates' => [$polygon['coordinates'], square(31.60)['coordinates']]])->geometry['type'])->toBe('MultiPolygon');

    // A hole inside the outline is fine.
    $withHole = square();
    $withHole['coordinates'][] = [[74.348, 31.518], [74.352, 31.518], [74.352, 31.522], [74.348, 31.522], [74.348, 31.518]];
    expect(BoundaryGeometry::parse($withHole)->vertexCount())->toBe(8);
});

it('rejects malformed or unsafe boundary geometry', function (mixed $input, string $message) {
    expect(fn () => BoundaryGeometry::parse($input))->toThrow(ValidationException::class, $message);
})->with([
    'not json' => ['{not json', 'not valid GeoJSON'],
    'a line' => [['type' => 'LineString', 'coordinates' => [[74.3, 31.5], [74.4, 31.6]]], 'Polygon or MultiPolygon'],
    'two features' => [['type' => 'FeatureCollection', 'features' => [['type' => 'Feature'], ['type' => 'Feature']]], 'exactly one'],
    'unclosed ring' => [['type' => 'Polygon', 'coordinates' => [[[74.3, 31.5], [74.4, 31.5], [74.4, 31.6], [74.3, 31.6]]]], 'not closed'],
    'too few points' => [['type' => 'Polygon', 'coordinates' => [[[74.3, 31.5], [74.4, 31.5], [74.3, 31.5], [74.3, 31.5]]]], 'at least 3 distinct'],
    'collinear' => [['type' => 'Polygon', 'coordinates' => [[[74.3, 31.5], [74.35, 31.5], [74.4, 31.5], [74.3, 31.5]]]], 'no area'],
    'bow tie' => [['type' => 'Polygon', 'coordinates' => [[[74.3, 31.5], [74.4, 31.6], [74.4, 31.5], [74.3, 31.6], [74.3, 31.5]]]], 'crosses itself'],
    'lat/lng swapped' => [['type' => 'Polygon', 'coordinates' => [[[31.5, 74.3], [31.6, 74.3], [31.6, 74.4], [31.5, 74.3]]]], 'outside Pakistan'],
    'text coordinates' => [['type' => 'Polygon', 'coordinates' => [[['a', 'b'], [74.4, 31.5], [74.4, 31.6], ['a', 'b']]]], 'malformed position'],
    'hole outside' => [['type' => 'Polygon', 'coordinates' => [square()['coordinates'][0], [[74.5, 31.7], [74.51, 31.7], [74.51, 31.71], [74.5, 31.7]]]], 'outside its polygon'],
]);

it('rejects boundaries with too many points', function () {
    $ring = collect(range(0, BoundaryGeometry::MAX_VERTICES))->map(fn ($i) => [74.35 + 0.01 * cos(2 * M_PI * $i / 2001), 31.52 + 0.01 * sin(2 * M_PI * $i / 2001)])->all();
    $ring[] = $ring[0];

    expect(fn () => BoundaryGeometry::parse(['type' => 'Polygon', 'coordinates' => [$ring]]))->toThrow(ValidationException::class, 'more than');
});

// ── Public boundaries ────────────────────────────────────────────────────

it('shows only published boundaries in view, with real listing counts', function () {
    $published = boundaryFor('Gulberg', 'published');
    $draft = boundaryFor('Model Town', 'draft', square(31.48, 74.32));
    $verified = boundaryFor('Johar Town', 'verified', square(31.47, 74.28));
    $farAway = boundaryFor('Bahria Town', 'published', square(31.36, 74.18));
    Property::factory()->count(2)->create(['area_id' => area('Gulberg')->id]);
    Property::factory()->status(PropertyStatus::Pending)->create(['area_id' => area('Gulberg')->id]);

    $features = $this->getJson(route('map.boundaries', ['north' => 31.56, 'south' => 31.44, 'east' => 74.40, 'west' => 74.25]))
        ->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->json('features');

    expect($features)->toHaveCount(1)
        ->and($features[0]['id'])->toBe($published->id)
        ->and($features[0]['properties'])->toMatchArray(['name' => 'Gulberg', 'city' => 'Lahore', 'listings' => 2])
        ->and($features[0]['geometry']['type'])->toBe('Polygon');

    $this->getJson(route('map.boundaries.show', $published))->assertOk()
        ->assertJsonPath('listings', 2)->assertJsonPath('name', 'Gulberg')
        ->assertJsonPath('mapUrl', route('properties.map', ['area' => area('Gulberg')->slug]));
    $this->getJson(route('map.boundaries.show', $draft))->assertNotFound();
    $this->getJson(route('map.boundaries.show', $verified))->assertNotFound();
    expect($farAway->status)->toBe('published');

    // Zoomed out to the whole country: nothing is sent.
    $this->getJson(route('map.boundaries', ['north' => 37, 'south' => 24, 'east' => 77, 'west' => 61]))->assertJsonPath('zoomIn', true)->assertJsonCount(0, 'features');
    $this->getJson(route('map.boundaries', ['north' => 'x']))->assertUnprocessable();
});

// ── Admin boundary management ───────────────────────────────────────────

it('keeps boundary management to admins', function () {
    $boundary = boundaryFor('Gulberg', 'verified');
    $payload = ['area_id' => area('Gulberg')->id, 'geometry' => json_encode(square()), 'source' => 'admin_drawn'];

    $this->get(route('admin.boundaries.index'))->assertRedirect(route('login'));
    $this->post(route('admin.boundaries.store'), $payload)->assertRedirect(route('login'));

    foreach ([User::factory()->create(), User::factory()->dealer(DealerStatus::Verified)->create()] as $user) {
        $this->actingAs($user)->get(route('admin.boundaries.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.boundaries.create', ['area' => area('Gulberg')->id]))->assertForbidden();
        $this->actingAs($user)->post(route('admin.boundaries.store'), $payload)->assertForbidden();
        $this->actingAs($user)->patch(route('admin.boundaries.transition', $boundary), ['action' => 'publish'])->assertForbidden();
    }

    expect(AreaBoundary::count())->toBe(1)->and($boundary->fresh()->status)->toBe('verified');
});

it('lets an admin draw, verify and publish an outline, replacing the old one', function () {
    $admin = User::factory()->admin()->create();
    $old = boundaryFor('Gulberg', 'published');

    $this->actingAs($admin)->get(route('admin.boundaries.create', ['area' => area('Gulberg')->id]))->assertOk()->assertSee('data-boundary-editor', false);
    $this->actingAs($admin)->post(route('admin.boundaries.store'), [
        'area_id' => area('Gulberg')->id, 'geometry' => json_encode(square(31.52, 74.35, 0.02)), 'source' => 'admin_drawn', 'notes' => 'Checked on site',
    ])->assertSessionHasNoErrors();

    $new = AreaBoundary::latest('id')->first();
    expect($new)->status->toBe('draft')->created_by->toBe($admin->id)->vertex_count->toBe(4)
        ->and($new->area_id)->toBe(area('Gulberg')->id);

    // Publishing straight from draft is not allowed.
    $this->actingAs($admin)->patch(route('admin.boundaries.transition', $new), ['action' => 'publish'])->assertSessionHasErrors('action');
    expect($new->fresh()->status)->toBe('draft');

    $this->actingAs($admin)->patch(route('admin.boundaries.transition', $new), ['action' => 'verify'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->patch(route('admin.boundaries.transition', $new), ['action' => 'publish'])->assertSessionHasNoErrors();

    expect($new->fresh())->status->toBe('published')->verified_by->toBe($admin->id)->published_by->toBe($admin->id)
        ->and($old->fresh()->status)->toBe('archived')
        ->and(AreaBoundary::published()->where('area_id', area('Gulberg')->id)->count())->toBe(1)
        ->and(AuditLog::where('action', 'like', 'boundary.%')->pluck('action')->all())
        ->toContain('boundary.created', 'boundary.verified', 'boundary.published', 'boundary.archived');

    // Unpublishing takes it off the public map again.
    $this->actingAs($admin)->patch(route('admin.boundaries.transition', $new), ['action' => 'unpublish']);
    expect($new->fresh()->status)->toBe('verified');
});

it('validates outlines on save and only edits drafts', function () {
    $admin = User::factory()->admin()->create();
    $draft = boundaryFor('Gulberg', 'draft');
    $published = boundaryFor('Model Town', 'published', square(31.48, 74.32));
    $bowTie = json_encode(['type' => 'Polygon', 'coordinates' => [[[74.3, 31.5], [74.4, 31.6], [74.4, 31.5], [74.3, 31.6], [74.3, 31.5]]]]);

    $this->actingAs($admin)->post(route('admin.boundaries.store'), ['area_id' => area('Johar Town')->id, 'geometry' => $bowTie, 'source' => 'admin_drawn'])
        ->assertSessionHasErrors('geometry');
    $this->actingAs($admin)->post(route('admin.boundaries.store'), ['area_id' => area('Johar Town')->id, 'geometry' => json_encode(square()), 'source' => 'openstreetmap'])
        ->assertSessionHasErrors('source_reference');
    expect(AreaBoundary::count())->toBe(2);

    $this->actingAs($admin)->patch(route('admin.boundaries.update', $draft), ['geometry' => json_encode(square(31.52, 74.35, 0.03)), 'source' => 'admin_drawn'])
        ->assertSessionHasNoErrors();
    expect($draft->fresh()->max_lat)->toBe(31.55);

    $this->actingAs($admin)->patch(route('admin.boundaries.update', $published), ['geometry' => json_encode(square()), 'source' => 'admin_drawn'])->assertStatus(409);

    // Revising a published outline starts a new draft; the published one stays as it was.
    $this->actingAs($admin)->post(route('admin.boundaries.duplicate', $published))->assertRedirect();
    expect(AreaBoundary::where('area_id', area('Model Town')->id)->pluck('status')->sort()->values()->all())->toBe(['draft', 'published']);
});

it('only accepts outlines for existing active locations in the launch cities', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.boundaries.store'), ['area_id' => 999999, 'geometry' => json_encode(square()), 'source' => 'admin_drawn'])->assertNotFound();
    $this->actingAs($admin)->get(route('admin.boundaries.create', ['area' => 999999]))->assertNotFound();
    expect(AreaBoundary::count())->toBe(0);
});
