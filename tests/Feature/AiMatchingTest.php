<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Area;
use App\Models\Property;
use App\Models\User;
use App\Services\Ai\RulesRequirementParser;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\PropertyMatcher;

it('parses a free-text requirement', function () {
    $criteria = app(RulesRequirementParser::class)->parse('Looking to buy a 10 marla house in DHA Phase 6 with 4 bedrooms, budget 6 crore');

    expect($criteria->purpose)->toBe(Purpose::Sale)
        ->and($criteria->type)->toBe(PropertyType::House)
        ->and($criteria->areaIds)->toBe([area('DHA Phase 6')->id])
        ->and($criteria->maxPrice)->toBe(60_000_000)
        ->and($criteria->minAreaSqft)->toBe(2250)
        ->and($criteria->bedrooms)->toBe(4);
});

it('parses Roman Urdu and rent budgets', function () {
    $criteria = app(RulesRequirementParser::class)->parse('Gulberg mein flat kiraye par chahiye 1.5 lakh tak');

    expect($criteria->purpose)->toBe(Purpose::Rent)
        ->and($criteria->type)->toBe(PropertyType::Apartment)
        ->and($criteria->areaIds)->toBe([area('Gulberg')->id])
        ->and($criteria->maxPrice)->toBe(150_000);
});

it('treats a bare DHA mention as every DHA phase', function () {
    $criteria = app(RulesRequirementParser::class)->parse('house in DHA');

    expect($criteria->areaIds)->toHaveCount(Area::where('name', 'like', 'DHA%')->count());
});

it('returns at most three eligible matches and enforces hard constraints', function () {
    $gulberg = area('Gulberg')->id;
    $good = Property::factory()->count(4)->create(['area_id' => $gulberg, 'price' => 40_000_000]);
    $overBudget = Property::factory()->create(['title' => 'Over Budget', 'area_id' => $gulberg, 'price' => 80_000_000]);
    $wrongType = Property::factory()->create(['title' => 'Wrong Type', 'type' => PropertyType::Shop, 'area_id' => $gulberg, 'price' => 30_000_000, 'bedrooms' => null]);
    $rental = Property::factory()->create(['title' => 'A Rental', 'purpose' => Purpose::Rent, 'area_id' => $gulberg, 'price' => 100_000]);
    $draft = Property::factory()->status(PropertyStatus::Rejected)->create(['title' => 'Rejected One', 'area_id' => $gulberg, 'price' => 40_000_000]);

    $result = app(PropertyMatcher::class)->match(new MatchCriteria(purpose: Purpose::Sale, type: PropertyType::House, areaIds: [$gulberg], maxPrice: 50_000_000));

    $ids = $result->matches->map(fn ($m) => $m->property->id);
    expect($result->matches)->toHaveCount(3)
        ->and($ids->intersect($good->pluck('id')))->toHaveCount(3)
        ->and($ids)->not->toContain($overBudget->id, $wrongType->id, $rental->id, $draft->id);
});

it('explains shortfalls and never calls a partial fit strong', function () {
    Property::factory()->create(['area_id' => area('Johar Town')->id, 'price' => 30_000_000, 'bedrooms' => 2, 'area_size' => 5]);

    $match = app(PropertyMatcher::class)->match(new MatchCriteria(purpose: Purpose::Sale, type: PropertyType::House, areaIds: [area('Gulberg')->id], maxPrice: 50_000_000, minAreaSqft: 2250, bedrooms: 4))->matches->sole();

    expect($match->label())->not->toBe('Strong match')
        ->and($match->caveats)->toContain('Fewer bedrooms than requested')
        ->and(implode(' ', $match->caveats))->toContain('Outside your preferred areas');
});

it('boosts verified dealers', function () {
    $verified = User::factory()->dealer(DealerStatus::Verified)->create();
    $a = Property::factory()->create(['area_id' => area('Gulberg')->id]);
    $b = Property::factory()->forDealer($verified)->create(['area_id' => area('Gulberg')->id]);

    $result = app(PropertyMatcher::class)->match(new MatchCriteria(purpose: Purpose::Sale, areaIds: [area('Gulberg')->id]));

    expect($result->matches->first()->property->id)->toBe($b->id);
});

it('offers a useful fallback when nothing fits the budget', function () {
    Property::factory()->create(['price' => 70_000_000]);

    $result = app(PropertyMatcher::class)->match(new MatchCriteria(purpose: Purpose::Sale, type: PropertyType::House, maxPrice: 10_000_000));

    expect($result->isEmpty())->toBeTrue()
        ->and($result->hints[0])->toContain('PKR 7 Crore');
});

it('answers the AI finder chat with real listings as JSON', function () {
    Property::factory()->create(['title' => 'Gulberg Chat Match', 'area_id' => area('Gulberg')->id, 'price' => 40_000_000]);

    $this->postJson('/ai-property-finder', ['message' => '10 marla house in Gulberg under 5 crore'])
        ->assertOk()
        ->assertJsonPath('reply', fn ($reply) => str_contains($reply, 'best option'))
        ->assertJson(fn ($json) => $json->where('html', fn ($html) => str_contains($html, 'Gulberg Chat Match'))->etc());
});

it('asks for more detail when the message has no requirement', function () {
    $this->postJson('/ai-property-finder', ['message' => 'hello'])
        ->assertOk()
        ->assertJsonPath('reply', fn ($reply) => str_contains($reply, 'Tell me a little more'));
});

it('works without JavaScript through a GET search', function () {
    Property::factory()->create(['title' => 'No JS Match', 'area_id' => area('Gulberg')->id]);

    $this->get('/ai-property-finder?message=house+in+Gulberg')->assertOk()->assertSee('No JS Match');
});
