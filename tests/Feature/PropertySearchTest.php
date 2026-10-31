<?php

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Property;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->house = Property::factory()->create(['title' => 'Gulberg Family House', 'area_id' => area('Gulberg')->id, 'price' => 40_000_000, 'bedrooms' => 5]);
    $this->flat = Property::factory()->create(['title' => 'Johar Town Apartment', 'type' => PropertyType::Apartment, 'area_id' => area('Johar Town')->id, 'price' => 15_000_000, 'bedrooms' => 2]);
    $this->rental = Property::factory()->create(['title' => 'DHA Portion Rental', 'purpose' => Purpose::Rent, 'type' => PropertyType::UpperPortion, 'area_id' => area('DHA Phase 5')->id, 'price' => 120_000]);
    $this->plot = Property::factory()->create(['title' => 'Lake City Plot', 'type' => PropertyType::ResidentialPlot, 'area_id' => area('Lake City')->id, 'price' => 27_000_000, 'bedrooms' => null, 'area_size' => 1, 'area_unit' => 'kanal']);
    $this->draft = Property::factory()->status(PropertyStatus::Draft)->create(['title' => 'Hidden Draft Listing']);
});

it('only lists published properties', function () {
    $this->get('/properties')->assertSee('Gulberg Family House')->assertDontSee('Hidden Draft Listing');
});

it('filters by purpose, type and area', function () {
    $this->get('/properties?purpose=rent')->assertSee('DHA Portion Rental')->assertDontSee('Gulberg Family House');
    $this->get('/properties?type=apartment')->assertSee('Johar Town Apartment')->assertDontSee('Gulberg Family House');
    $this->get('/properties?area='.area('Gulberg')->slug)->assertSee('Gulberg Family House')->assertDontSee('Johar Town Apartment');
    $this->get('/properties?group=plots')->assertSee('Lake City Plot')->assertDontSee('Gulberg Family House');
});

it('resolves a typed location to an area', function () {
    $this->get('/properties?location=Johar+Town')->assertSee('Johar Town Apartment')->assertDontSee('Gulberg Family House')->assertSee('in Johar Town, Lahore');
});

it('filters by price, bedrooms and normalised area', function () {
    $this->get('/properties?max_price=20000000')->assertSee('Johar Town Apartment')->assertDontSee('Gulberg Family House');
    $this->get('/properties?min_price=30000000')->assertSee('Gulberg Family House')->assertDontSee('Johar Town Apartment');
    $this->get('/properties?beds=4')->assertSee('Gulberg Family House')->assertDontSee('Johar Town Apartment');
    // 1 kanal = 20 marla, so a 15 marla minimum includes the kanal plot but not the 10 marla homes.
    $this->get('/properties?min_area=15&unit=marla')->assertSee('Lake City Plot')->assertDontSee('Gulberg Family House');
});

it('ignores invalid filter values instead of failing', function () {
    $this->get('/properties?purpose=steal&type=castle&beds=99&sort=random&min_price=abc')
        ->assertOk()
        ->assertSee('Gulberg Family House');
});

it('sorts by price', function () {
    $response = $this->get('/properties?purpose=sale&sort=price_asc')->assertOk();
    $content = $response->getContent();

    expect(strpos($content, 'Johar Town Apartment'))->toBeLessThan(strpos($content, 'Gulberg Family House'));
});

it('keeps filters in pagination links', function () {
    Property::factory()->count(14)->create(['area_id' => area('Gulberg')->id]);

    $this->get('/properties?area='.area('Gulberg')->slug)
        ->assertOk()
        ->assertSee('area='.area('Gulberg')->slug.'&amp;page=2', false);
});

it('only puts listings with real coordinates on the map', function () {
    $this->house->forceFill(['latitude' => 31.52, 'longitude' => 74.35])->save();

    $response = $this->get('/map')->assertOk();

    $markers = json_decode(html_entity_decode(Str::betweenFirst($response->getContent(), 'data-markers="', '"')), true);

    expect($markers)->toHaveCount(1)
        ->and($markers[0]['id'])->toBe($this->house->id)
        ->and($markers[0]['label'])->toBe('4 Cr');
});

it('supports checkbox filters for several types and areas', function () {
    $this->get('/properties?type[]=house&type[]=apartment')
        ->assertSee('Gulberg Family House')->assertSee('Johar Town Apartment')->assertDontSee('Lake City Plot');

    $this->get('/properties?area[]='.area('Gulberg')->slug.'&area[]='.area('Johar Town')->slug)
        ->assertSee('Gulberg Family House')->assertSee('Johar Town Apartment')->assertDontSee('DHA Portion Rental');
});

it('maps homepage tabs and size presets to filters', function () {
    $this->get('/properties?tab=rent')->assertSee('DHA Portion Rental')->assertDontSee('Gulberg Family House');
    $this->get('/properties?tab=plots')->assertSee('Lake City Plot')->assertDontSee('Gulberg Family House');
    $this->get('/properties?size=1k')->assertSee('Lake City Plot')->assertDontSee('Gulberg Family House');
    $this->get('/properties?size=10m')->assertSee('Gulberg Family House')->assertDontSee('Lake City Plot');
});

it('treats the city name as "anywhere in the city"', function () {
    $this->get('/properties?location=Lahore')->assertSee('Gulberg Family House')->assertSee('Lake City Plot');
});

it('shows listing counts next to filter options', function () {
    $this->get('/properties')->assertOk()->assertSeeInOrder(['Gulberg', '1']);
});
