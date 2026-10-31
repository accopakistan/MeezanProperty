<?php

use App\Models\Area;
use Database\Seeders\AreaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against an in-memory SQLite database (see phpunit.xml),
| refreshed for every test. The Lahore areas reference data is seeded first.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(AreaSeeder::class))
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function area(string $name): Area
{
    return Area::where('name', $name)->firstOrFail();
}

/** A valid dealer listing form submission. */
function listingPayload(array $overrides = []): array
{
    return array_merge([
        'title' => '10 Marla House with Basement in Gulberg',
        'purpose' => 'sale',
        'type' => 'house',
        'city' => 'Lahore',
        'area_id' => area('Gulberg')->id,
        'price' => 55000000,
        'area_size' => 10,
        'area_unit' => 'marla',
        'bedrooms' => 4,
        'bathrooms' => 5,
        'description' => str_repeat('Spacious double storey house with basement, close to the main boulevard. ', 2),
        'amenities' => ['parking', 'gas'],
        'authorization_type' => 'authorized_agent',
        'authorization_confirmed' => '1',
    ], $overrides);
}

/** Decoded data-markers payload from a page with a map. */
function markersOn(string $html): array
{
    preg_match('/data-markers="([^"]*)"/', $html, $m);

    return json_decode(html_entity_decode($m[1] ?? '[]'), true);
}
