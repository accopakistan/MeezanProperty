<?php

use App\Models\Area;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('plots exact pins and groups listings without a pin at their area centre', function () {
    area('Gulberg')->update(['latitude' => 31.512, 'longitude' => 74.343]);
    Property::factory()->create(['title' => 'Pinned House', 'latitude' => 31.52, 'longitude' => 74.35, 'price' => 25_000_000]);
    Property::factory()->count(2)->sequence(['price' => 30_000_000], ['price' => 18_000_000])
        ->create(['area_id' => area('Gulberg')->id, 'latitude' => null, 'longitude' => null]);
    // No exact pin and no area centre: stays in the list only.
    Property::factory()->create(['area_id' => area('Model Town')->id, 'latitude' => null, 'longitude' => null]);

    $markers = collect(markersOn($this->get(route('properties.map'))->assertOk()->getContent()));

    $pin = $markers->firstWhere('kind', 'pin');
    expect($pin)->title->toBe('Pinned House')->label->toBe('2.5 Cr');

    $group = $markers->firstWhere('kind', 'area');
    expect($markers)->toHaveCount(2)
        ->and($group)->title->toBe('Gulberg')->count->toBe(2)->label->toBe('2 listings')->from->toBe('1.8 Cr+')
        ->and($group['lat'])->toBe(31.512)
        ->and($group['ids'])->toHaveCount(2)
        ->and($group['url'])->toContain('area=gulberg-lahore');
});

it('plots every matching result, not just the current page', function () {
    area('Gulberg')->update(['latitude' => 31.512, 'longitude' => 74.343]);
    Property::factory()->count(14)->create(['area_id' => area('Gulberg')->id, 'latitude' => null, 'longitude' => null]);

    $markers = markersOn($this->get(route('properties.map'))->getContent());

    expect($markers[0]['count'])->toBe(14);
});

it('shows an approximate area map on a listing without an exact pin', function () {
    area('Gulberg')->update(['latitude' => 31.512, 'longitude' => 74.343]);
    $property = Property::factory()->create(['area_id' => area('Gulberg')->id, 'latitude' => null, 'longitude' => null]);

    $this->get(route('properties.show', $property))
        ->assertOk()
        ->assertSee('Approximate: shown at the centre of Gulberg', false);
});

it('fills area centres from OpenStreetMap and rejects matches outside the city', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::sequence()
            ->push([['lat' => '31.4616343', 'lon' => '74.2825191']])
            ->push([['lat' => '24.8607', 'lon' => '67.0011']]) // Karachi: too far from Lahore
            ->whenEmpty(Http::response([])),
    ]);
    Area::query()->whereNotIn('name', ['Johar Town', 'Model Town'])->update(['latitude' => 31.5, 'longitude' => 74.3]);

    $this->artisan('meezan:geocode-areas')->assertSuccessful();

    expect(area('Johar Town')->only('latitude', 'longitude', 'coordinates_source'))
        ->toBe(['latitude' => 31.4616343, 'longitude' => 74.2825191, 'coordinates_source' => 'openstreetmap'])
        ->and(area('Model Town')->latitude)->toBeNull();
});

it('lets admins set an area centre by hand', function () {
    $gulberg = area('Gulberg');

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.areas.update', $gulberg), ['name' => 'Gulberg', 'city' => 'Lahore', 'latitude' => '31.51', 'longitude' => '74.34'])
        ->assertSessionHasNoErrors();

    expect($gulberg->fresh())->latitude->toBe(31.51)->coordinates_source->toBe('manual');
});
