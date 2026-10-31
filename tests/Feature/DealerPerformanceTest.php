<?php

use App\Enums\DealerStatus;
use App\Models\Inquiry;
use App\Models\Property;
use App\Models\User;
use App\Services\DealerPerformance;
use Illuminate\Support\Facades\DB;

function inquiryFor(Property $property, array $attributes = []): Inquiry
{
    $inquiry = new Inquiry(['name' => 'Buyer', 'email' => uniqid().'@example.com', 'phone' => '03001112222', 'message' => 'Interested in this listing.']);
    $inquiry->forceFill(array_merge(['property_id' => $property->id, 'recipient_id' => $property->user_id], $attributes))->save();

    return $inquiry;
}

it('records one daily view per visitor session', function () {
    $property = Property::factory()->create();

    $this->get(route('properties.show', $property));
    $this->get(route('properties.show', $property));

    expect(DB::table('property_view_stats')->where('property_id', $property->id)->value('views'))->toBe(1)
        ->and($property->fresh()->views)->toBe(1);

    // A new session counts again on the same day row.
    $this->flushSession();
    $this->get(route('properties.show', $property));
    expect(DB::table('property_view_stats')->where('property_id', $property->id)->sole())->views->toBe(2);
});

it('does not count a dealer viewing their own listing', function () {
    $property = Property::factory()->create();

    $this->actingAs($property->dealer)->get(route('properties.show', $property));

    expect(DB::table('property_view_stats')->count())->toBe(0);
});

it('reports views, saves, inquiries and response times from the dealer’s own records only', function () {
    $dealer = User::factory()->dealer()->create();
    $mine = Property::factory()->forDealer($dealer)->create();
    $theirs = Property::factory()->create();

    DB::table('property_view_stats')->insert([
        ['property_id' => $mine->id, 'date' => now()->toDateString(), 'views' => 40],
        ['property_id' => $mine->id, 'date' => now()->subDays(40)->toDateString(), 'views' => 99],
        ['property_id' => $theirs->id, 'date' => now()->toDateString(), 'views' => 500],
    ]);
    User::factory()->create()->savedProperties()->attach($mine->id);
    inquiryFor($mine, ['created_at' => now()->subHours(5), 'responded_at' => now()->subHours(3), 'status' => 'contacted']);
    inquiryFor($mine);
    inquiryFor($theirs);

    $report = app(DealerPerformance::class)->for($dealer, 30);

    expect($report['kpis'])
        ->views->toBe(40)
        ->saves->toBe(1)
        ->inquiries->toBe(2)
        ->conversion->toBe(5.0)
        ->responseRate->toBe(50)
        ->medianResponseHours->toBe(2.0);
    expect($report['rows'])->toHaveCount(1);

    // 90 days includes the older view row.
    expect(app(DealerPerformance::class)->for($dealer, 90)['kpis']['views'])->toBe(139);
});

it('stamps the first response when a dealer updates an inquiry', function () {
    $property = Property::factory()->create();
    $inquiry = inquiryFor($property);

    $this->actingAs($property->dealer)->patch(route('dealer.inquiries.update', $inquiry), ['status' => 'contacted']);
    $first = $inquiry->fresh()->responded_at;
    $this->travel(2)->hours();
    $this->actingAs($property->dealer)->patch(route('dealer.inquiries.update', $inquiry), ['status' => 'closed']);

    expect($first)->not->toBeNull()->and($inquiry->fresh()->responded_at->equalTo($first))->toBeTrue();
});

it('suggests concrete improvements per listing', function () {
    $dealer = User::factory()->dealer()->create();
    Property::factory()->forDealer($dealer)->create(['description' => 'Short text here for a listing that needs work.', 'expires_at' => now()->addDays(5)]);

    $issues = app(DealerPerformance::class)->for($dealer)['rows'][0]['issues'];

    expect(implode(' | ', $issues))
        ->toContain('No photos')
        ->toContain('No map location')
        ->toContain('Covered area missing')
        ->toContain('Short description')
        ->toContain('renew to stay visible');
});

it('compares price per marla with the area only when there is enough data', function () {
    $dealer = User::factory()->dealer()->create();
    $gulberg = area('Gulberg')->id;
    $mine = Property::factory()->forDealer($dealer)->create(['area_id' => $gulberg, 'price' => 60_000_000]);

    expect(app(DealerPerformance::class)->for($dealer)['rows'][0]['vsArea'])->toBeNull();

    // 10 marla houses at 5 crore -> 50 lakh/marla; with mine the median stays 50 lakh.
    Property::factory()->count(5)->create(['area_id' => $gulberg, 'price' => 50_000_000]);

    expect(app(DealerPerformance::class)->for($dealer)['rows']->firstWhere('property.id', $mine->id)['vsArea'])->toBe(20);
});

it('renders the report and CSV for dealers only', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $property = Property::factory()->forDealer($dealer)->create(['title' => 'Report Test House']);
    Property::factory()->create(['title' => 'Someone Else House']);

    $this->actingAs($dealer)->get(route('dealer.performance', ['days' => 7]))
        ->assertOk()->assertSee('Report Test House')->assertDontSee('Someone Else House')->assertSee('Daily view counts are recorded');

    $csv = $this->actingAs($dealer)->get(route('dealer.performance.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('Report Test House')->toContain($property->reference)->not->toContain('Someone Else House');

    $this->actingAs(User::factory()->create())->get(route('dealer.performance'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dealers.show', $dealer->dealerProfile))->assertSee('Performance, last 30 days');
});
