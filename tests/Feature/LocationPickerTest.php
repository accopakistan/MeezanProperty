<?php

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Http;

// Model Town, Lahore: a real point inside Pakistan.
const PIN_LAT = '31.4805123';
const PIN_LNG = '74.3239456';

it('creates a listing with an exact location from the picker', function () {
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)
        ->post(route('dealer.properties.store'), listingPayload(['action' => 'draft', 'latitude' => PIN_LAT, 'longitude' => PIN_LNG]))
        ->assertSessionHasNoErrors();

    expect(Property::sole())->latitude->toBe(31.4805123)->longitude->toBe(74.3239456);
});

it('creates a listing without an exact location and never fills in the area centre', function () {
    area('Gulberg')->update(['latitude' => 31.512, 'longitude' => 74.343]);
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)
        ->post(route('dealer.properties.store'), listingPayload(['action' => 'draft', 'latitude' => '', 'longitude' => '']))
        ->assertSessionHasNoErrors();

    expect(Property::sole())->latitude->toBeNull()->longitude->toBeNull();
});

it('rejects partial, malformed and out-of-country coordinates', function (array $coords, string $field) {
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)
        ->post(route('dealer.properties.store'), listingPayload(['action' => 'draft'] + $coords))
        ->assertSessionHasErrors($field);

    expect(Property::count())->toBe(0);
})->with([
    'latitude only' => [['latitude' => PIN_LAT, 'longitude' => ''], 'longitude'],
    'longitude only' => [['latitude' => '', 'longitude' => PIN_LNG], 'latitude'],
    'latitude beyond 90' => [['latitude' => '91', 'longitude' => PIN_LNG], 'latitude'],
    'text latitude' => [['latitude' => 'north', 'longitude' => PIN_LNG], 'latitude'],
    'pair typed into one field' => [['latitude' => '31.48, 74.32', 'longitude' => PIN_LNG], 'latitude'],
    'text longitude' => [['latitude' => PIN_LAT, 'longitude' => '74°E'], 'longitude'],
    'north of Pakistan' => [['latitude' => '40.1', 'longitude' => PIN_LNG], 'latitude'],
    'Dubai' => [['latitude' => '25.2048', 'longitude' => '55.2708'], 'longitude'],
    'London' => [['latitude' => '51.5072', 'longitude' => '-0.1276'], 'latitude'],
]);

it('shows the saved pin on the edit form and the area centres only as map data', function () {
    area('Gulberg')->update(['latitude' => 31.512, 'longitude' => 74.343]);
    $dealer = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($dealer)->status(PropertyStatus::Draft)
        ->create(['area_id' => area('Gulberg')->id, 'latitude' => 31.4805123, 'longitude' => 74.3239456]);

    $this->actingAs($dealer)->get(route('dealer.properties.edit', $property))
        ->assertOk()
        ->assertSee('data-location-picker', false)
        ->assertSee('value="31.4805123"', false)
        ->assertSee('value="74.3239456"', false)
        ->assertSee('Exact location set');

    // A new listing starts empty even though the area has a centre point.
    $this->actingAs($dealer)->get(route('dealer.properties.create'))
        ->assertOk()
        ->assertSee('Exact location not set')
        ->assertDontSee('value="31.512"', false);
});

it('updates and clears the pin when editing', function () {
    $dealer = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($dealer)->status(PropertyStatus::Draft)->create(['latitude' => 31.5, 'longitude' => 74.3]);

    $this->actingAs($dealer)
        ->put(route('dealer.properties.update', $property), listingPayload(['action' => 'draft', 'latitude' => PIN_LAT, 'longitude' => PIN_LNG]))
        ->assertSessionHasNoErrors();
    expect($property->fresh())->latitude->toBe(31.4805123)->longitude->toBe(74.3239456);

    // The first save renamed the listing, so route by its new slug.
    $this->actingAs($dealer)
        ->put(route('dealer.properties.update', $property->fresh()), listingPayload(['action' => 'draft', 'latitude' => '', 'longitude' => '']))
        ->assertSessionHasNoErrors();
    expect($property->fresh())->latitude->toBeNull()->longitude->toBeNull();
});

it('keeps saved coordinates when a request leaves the location fields out', function () {
    $dealer = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($dealer)->status(PropertyStatus::Draft)->create(['latitude' => 31.5, 'longitude' => 74.3]);

    $this->actingAs($dealer)->put(route('dealer.properties.update', $property), listingPayload(['action' => 'draft']))->assertSessionHasNoErrors();

    expect($property->fresh())->latitude->toBe(31.5)->longitude->toBe(74.3);
});

it('sends a published listing back to review when its pin moves', function () {
    $dealer = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($dealer)->create(['latitude' => 31.5, 'longitude' => 74.3]);

    $this->actingAs($dealer)->put(route('dealer.properties.update', $property), listingPayload(['latitude' => PIN_LAT, 'longitude' => PIN_LNG]));

    expect($property->fresh()->status)->toBe(PropertyStatus::Pending);
});

it('does not let another dealer move a listing’s pin', function () {
    $owner = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($owner)->create(['latitude' => 31.5, 'longitude' => 74.3]);

    $this->actingAs(User::factory()->dealer()->create())
        ->put(route('dealer.properties.update', $property), listingPayload(['latitude' => PIN_LAT, 'longitude' => PIN_LNG]))
        ->assertForbidden();
    $this->actingAs(User::factory()->create())
        ->put(route('dealer.properties.update', $property), listingPayload(['latitude' => PIN_LAT, 'longitude' => PIN_LNG]))
        ->assertForbidden();

    expect($property->fresh())->latitude->toBe(31.5)->longitude->toBe(74.3);
});

it('plots a saved pin at its exact point on the public map and links to the listing', function () {
    area('Gulberg')->update(['latitude' => 31.512, 'longitude' => 74.343]);
    $pinned = Property::factory()->create(['title' => 'Pinned In Model Town', 'latitude' => 31.4805123, 'longitude' => 74.3239456]);
    Property::factory()->create(['title' => 'No Pin In Gulberg', 'area_id' => area('Gulberg')->id, 'latitude' => null, 'longitude' => null]);

    $markers = collect(markersOn($this->get(route('properties.map'))->assertOk()->getContent()));

    $pin = $markers->firstWhere('kind', 'pin');
    expect($markers->where('kind', 'pin'))->toHaveCount(1)
        ->and($pin)->lat->toBe(31.4805123)->lng->toBe(74.3239456)->url->toBe(route('properties.show', $pinned))
        ->and($markers->firstWhere('kind', 'area'))->lat->toBe(31.512)->ids->toHaveCount(1);
    // Marker payloads carry listing facts only, never dealer contact details.
    expect(json_encode($markers))->not->toContain('@')->not->toContain('0300');
});

it('finds a pinned listing with the viewport and drawn-area searches', function () {
    Property::factory()->create(['title' => 'Pinned In Model Town', 'latitude' => 31.4805123, 'longitude' => 74.3239456]);
    Property::factory()->create(['title' => 'Pinned Far Away', 'latitude' => 31.30, 'longitude' => 74.10]);

    $this->get(route('properties.index', ['north' => 31.50, 'south' => 31.46, 'east' => 74.35, 'west' => 74.30]))
        ->assertSee('Pinned In Model Town')->assertDontSee('Pinned Far Away');
    $this->get(route('properties.index', ['poly' => '31.50,74.30;31.50,74.35;31.46,74.35;31.46,74.30']))
        ->assertSee('Pinned In Model Town')->assertDontSee('Pinned Far Away');
});

it('labels exact, approximate and missing locations on the listing page', function () {
    area('Gulberg')->update(['latitude' => 31.512, 'longitude' => 74.343]);
    $exact = Property::factory()->create(['latitude' => 31.4805123, 'longitude' => 74.3239456]);
    $approximate = Property::factory()->create(['area_id' => area('Gulberg')->id, 'latitude' => null, 'longitude' => null]);
    $none = Property::factory()->create(['area_id' => area('Model Town')->id, 'latitude' => null, 'longitude' => null]);

    $this->get(route('properties.show', $exact))->assertSee('Exact location')->assertSee('placed by the advertiser');
    $this->get(route('properties.show', $approximate))->assertSee('Approximate area')->assertDontSee('placed by the advertiser');
    $this->get(route('properties.show', $none))->assertSee('Location not provided')->assertDontSee('data-markers="[{', false);
});

it('shows reviewers the pin on the admin listing page', function () {
    $property = Property::factory()->status(PropertyStatus::Pending)->create(['latitude' => 31.4805123, 'longitude' => 74.3239456]);

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.properties.show', $property))
        ->assertOk()->assertSee('Exact location')->assertSee('31.4805123, 74.3239456');
});

it('searches places for dealers only and keeps results inside Pakistan', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
        ['display_name' => 'Block K, Model Town, Lahore', 'lat' => '31.4805', 'lon' => '74.3239', 'addresstype' => 'neighbourhood'],
        ['display_name' => 'Somewhere abroad', 'lat' => '25.2', 'lon' => '55.27'],
    ])]);
    $dealer = User::factory()->dealer()->create();

    $this->get(route('dealer.location-search', ['q' => 'Block K Model Town']))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->getJson(route('dealer.location-search', ['q' => 'Block K Model Town']))->assertForbidden();
    $this->actingAs($dealer)->getJson(route('dealer.location-search', ['q' => 'ab']))->assertUnprocessable();

    $this->actingAs($dealer)->getJson(route('dealer.location-search', ['q' => 'Block K Model Town']))
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.label', 'Block K, Model Town, Lahore')
        ->assertJsonPath('results.0.lat', 31.4805);

    // Repeat searches are served from the cache.
    $this->actingAs($dealer)->getJson(route('dealer.location-search', ['q' => 'block k  model town']))->assertOk();
    Http::assertSentCount(1);
});

it('returns no places instead of an error when the geocoder is down', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 503)]);

    $this->actingAs(User::factory()->dealer()->create())
        ->getJson(route('dealer.location-search', ['q' => 'Model Town']))
        ->assertOk()->assertJsonCount(0, 'results');
});
