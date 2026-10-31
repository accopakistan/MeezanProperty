<?php

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function cutPrice(Property $property, int $from, int $to, $when): void
{
    $property->priceChanges()->create(['old_price' => $from, 'new_price' => $to, 'changed_at' => $when]);
    $property->forceFill(['price' => $to])->saveQuietly();
}

it('filters by how recently a listing was published', function () {
    Property::factory()->create(['title' => 'Fresh Today House', 'published_at' => now()->subHours(3)]);
    Property::factory()->create(['title' => 'Last Week House', 'published_at' => now()->subDays(5)]);
    Property::factory()->create(['title' => 'Old Listing House', 'published_at' => now()->subDays(40)]);

    $this->get(route('properties.index', ['listed' => 1]))
        ->assertSee('Fresh Today House')->assertDontSee('Last Week House')->assertDontSee('Old Listing House')
        ->assertSee('Listed: last 24 hours');
    $this->get(route('properties.index', ['listed' => 7]))
        ->assertSee('Last Week House')->assertDontSee('Old Listing House');
    // Unknown values are ignored rather than failing.
    $this->get(route('properties.index', ['listed' => 999]))->assertOk()->assertSee('Old Listing House');
});

it('finds listings whose price was recently reduced to today’s price', function () {
    $cut = Property::factory()->create(['title' => 'Recently Cut House', 'price' => 60_000_000]);
    cutPrice($cut, 60_000_000, 52_000_000, now()->subDays(10));
    $raised = Property::factory()->create(['title' => 'Cut Then Raised House', 'price' => 60_000_000]);
    cutPrice($raised, 60_000_000, 50_000_000, now()->subDays(20));
    cutPrice($raised, 50_000_000, 58_000_000, now()->subDays(5));
    $oldCut = Property::factory()->create(['title' => 'Long Ago Cut House', 'price' => 40_000_000]);
    cutPrice($oldCut, 45_000_000, 40_000_000, now()->subDays(200));
    Property::factory()->create(['title' => 'Never Changed House']);

    $this->get(route('properties.index', ['reduced' => 1]))
        ->assertSee('Recently Cut House')
        ->assertDontSee('Cut Then Raised House')
        ->assertDontSee('Long Ago Cut House')
        ->assertDontSee('Never Changed House')
        ->assertSee('Price reduced');
});

it('badges reduced and new listings on cards without inventing either', function () {
    $cut = Property::factory()->create(['title' => 'Badge Cut House', 'price' => 60_000_000, 'published_at' => now()->subDays(30)]);
    cutPrice($cut, 60_000_000, 52_000_000, now()->subDays(3));
    Property::factory()->create(['title' => 'Badge New House', 'published_at' => now()->subDay()]);
    Property::factory()->create(['title' => 'Badge Plain House', 'published_at' => now()->subDays(30)]);

    $html = $this->get(route('properties.index'))->getContent();

    expect(substr_count($html, '>Price reduced<'))->toBe(1)
        ->and($html)->toContain('↓ from 6 Cr')
        ->and(substr_count($html, '>New<'))->toBe(1);
});

it('shows days on Meezan Property on the listing page', function () {
    $property = Property::factory()->create(['published_at' => now()->subDays(12)]);

    $this->get(route('properties.show', $property))->assertSee('Days on Meezan Property')->assertSee('12 days');
});

it('saves the new filters with a search', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->post(route('account.saved-searches.store'), ['filters' => json_encode(['listed' => '7', 'reduced' => '1', 'purpose' => 'sale'])]);

    expect($buyer->savedSearches()->sole()->filters)->toMatchArray(['listed' => '7', 'reduced' => '1']);
});

function overpassResponse(): array
{
    return ['elements' => [
        ['type' => 'node', 'lat' => 31.4810, 'lon' => 74.3240, 'tags' => ['amenity' => 'school', 'name' => 'Model Town Grammar School']],
        ['type' => 'way', 'center' => ['lat' => 31.4850, 'lon' => 74.3300], 'tags' => ['amenity' => 'hospital', 'name' => 'Sample General Hospital']],
        ['type' => 'node', 'lat' => 31.4806, 'lon' => 74.3236, 'tags' => ['amenity' => 'place_of_worship', 'religion' => 'muslim', 'name' => 'Jamia Masjid Block K']],
        ['type' => 'node', 'lat' => 31.4800, 'lon' => 74.3200, 'tags' => ['amenity' => 'place_of_worship', 'religion' => 'christian', 'name' => 'St. Example Church']],
        ['type' => 'node', 'lat' => 31.4820, 'lon' => 74.3210, 'tags' => ['leisure' => 'park']],
        ['type' => 'node', 'lat' => 31.4790, 'lon' => 74.3250, 'tags' => ['shop' => 'supermarket', 'name' => 'Corner Mart', 'name:en' => 'Corner Mart (English)']],
    ]];
}

it('lists named nearby places by category with straight-line distances', function () {
    Http::fake(['overpass-api.de/*' => Http::response(overpassResponse())]);
    $property = Property::factory()->create(['latitude' => 31.4805123, 'longitude' => 74.3239456]);

    $data = $this->getJson(route('properties.nearby', $property))->assertOk()->json();
    $byKey = collect($data['categories'])->keyBy('key');

    expect($data['available'])->toBeTrue()
        ->and($byKey['education']['places'][0]['name'])->toBe('Model Town Grammar School')
        ->and($byKey['education']['places'][0]['distance'])->toBeBetween(40, 80)
        ->and($byKey['health']['places'][0]['name'])->toBe('Sample General Hospital')
        ->and($byKey['mosques']['places'])->toHaveCount(1)
        ->and($byKey['shopping']['places'][0]['name'])->toBe('Corner Mart (English)')
        ->and($byKey['parks']['places'])->toBe([]);

    // Cached: a second request does not call the service again.
    $this->getJson(route('properties.nearby', $property))->assertOk();
    Http::assertSentCount(1);
});

it('reports the service as unavailable without caching the failure for long', function () {
    Http::fake(['overpass-api.de/*' => Http::sequence()->push('busy', 429)->push(overpassResponse())]);
    $property = Property::factory()->create(['latitude' => 31.4805123, 'longitude' => 74.3239456]);

    $this->getJson(route('properties.nearby', $property))->assertStatus(503)->assertJson(['available' => false]);
    $this->getJson(route('properties.nearby', $property))->assertStatus(503);
    Http::assertSentCount(1);

    $this->travel(11)->minutes();
    $this->getJson(route('properties.nearby', $property))->assertOk();
    Http::assertSentCount(2);
});

it('offers nearby places only for listings with an exact pin that the visitor may see', function () {
    Http::fake(['overpass-api.de/*' => Http::response(overpassResponse())]);
    $noPin = Property::factory()->create(['latitude' => null, 'longitude' => null]);
    $draft = Property::factory()->status(PropertyStatus::Draft)->create(['latitude' => 31.48, 'longitude' => 74.32]);

    $this->getJson(route('properties.nearby', $noPin))->assertNotFound();
    $this->getJson(route('properties.nearby', $draft))->assertNotFound();
    $this->get(route('properties.show', $noPin))->assertDontSee('Nearby Places');
    Http::assertNothingSent();

    // The listing's own dealer can preview it, nearby places included.
    $this->actingAs($draft->dealer)->getJson(route('properties.nearby', $draft))->assertOk();
    Http::assertSentCount(1);
    $this->actingAs($draft->dealer)->get(route('properties.show', $draft))->assertSee('Nearby Places')->assertSee('data-nearby=', false);
});

it('can be switched off', function () {
    config(['meezan.nearby.enabled' => false]);
    Http::fake();
    $property = Property::factory()->create(['latitude' => 31.48, 'longitude' => 74.32]);

    $this->get(route('properties.show', $property))->assertDontSee('Nearby Places');
    $this->getJson(route('properties.nearby', $property))->assertStatus(503);
    Http::assertNothingSent();
});
