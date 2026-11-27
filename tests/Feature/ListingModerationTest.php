<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Models\AppSetting;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ListingPublished;
use App\Notifications\ListingSubmittedForReview;
use App\Services\ListingModeration;
use Illuminate\Support\Facades\Notification;

/** A dealer's listing submitted through the form; returns the saved listing. */
function submitListing(User $dealer, array $overrides = []): Property
{
    test()->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['action' => 'submit'] + $overrides))->assertRedirect();

    return Property::where('user_id', $dealer->id)->latest('id')->firstOrFail();
}

it('publishes a clean listing as soon as it is submitted', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['action' => 'submit']))
        ->assertSessionHas('status', fn ($m) => str_contains($m, 'is live'));

    $property = Property::sole();
    expect($property->status)->toBe(PropertyStatus::Published)
        ->and($property->published_at)->not->toBeNull()
        ->and($property->expires_at->isFuture())->toBeTrue()
        ->and($property->flagged_at)->toBeNull();
    $this->get('/properties')->assertSee('10 Marla House with Basement in Gulberg');
    Notification::assertSentTo($admin, ListingPublished::class);
    Notification::assertNotSentTo($admin, ListingSubmittedForReview::class);
    expect((new ListingPublished($property))->via($admin))->toBe(['database']);
});

it('holds a listing the checks flag for an admin', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $dealer = User::factory()->dealer()->create();
    Property::factory()->forDealer($dealer)->count(15)->create(['submitted_at' => now()]);

    $property = submitListing($dealer, ['title' => 'Sixteenth House Listed Today In Gulberg']);

    expect($property->status)->toBe(PropertyStatus::Pending)
        ->and($property->review_flags)->toHaveKey('rapid_submissions');
    Notification::assertSentTo($admin, ListingSubmittedForReview::class);
});

it('holds new listings once an unverified dealer reaches the live limit', function () {
    config(['meezan.listing.unverified_live_limit' => 2]);
    $unverified = User::factory()->dealer()->create();
    $verified = User::factory()->dealer(DealerStatus::Verified)->create();
    foreach ([$unverified, $verified] as $dealer) {
        Property::factory()->forDealer($dealer)->count(2)->create();
    }

    expect(submitListing($unverified)->status)->toBe(PropertyStatus::Pending)
        // A different listing, so the duplicate check does not hold it.
        ->and(submitListing($verified, ['title' => '1 Kanal Corner House In Gulberg', 'price' => 98000000, 'area_size' => 20])->status)->toBe(PropertyStatus::Published);
});

it('keeps a live listing live after a minor edit', function () {
    $dealer = User::factory()->dealer()->create();
    $property = submitListing($dealer);

    $this->actingAs($dealer)->put(route('dealer.properties.update', $property), listingPayload([
        'description' => str_repeat('Freshly painted double storey house with basement near the boulevard. ', 2),
        'price' => 52000000,
    ]))->assertSessionHas('status', 'Changes saved. The listing is still live.');

    expect($property->fresh()->status)->toBe(PropertyStatus::Published);
});

it('sends a live listing back to review after a large price change', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $dealer = User::factory()->dealer()->create();
    $property = submitListing($dealer);

    $this->actingAs($dealer)->put(route('dealer.properties.update', $property), listingPayload(['price' => 20000000]));

    $property->refresh();
    expect($property->status)->toBe(PropertyStatus::Pending)
        ->and($property->review_flags)->toHaveKey('price_change');
    Notification::assertSentTo($admin, ListingSubmittedForReview::class);
});

it('reviews every listing and every edit when the owner chooses so', function () {
    $dealer = User::factory()->dealer()->create();
    $live = submitListing($dealer);
    AppSetting::put(ListingModeration::SETTING, ListingModeration::REVIEW_ALL);

    expect(submitListing($dealer, ['title' => 'Another House With Basement In Gulberg'])->status)->toBe(PropertyStatus::Pending);

    $this->actingAs($dealer)->put(route('dealer.properties.update', $live), listingPayload([
        'description' => str_repeat('Freshly painted double storey house with basement near the boulevard. ', 2),
    ]));
    expect($live->fresh()->status)->toBe(PropertyStatus::Pending);
});

it('lets only an admin switch listing moderation', function () {
    $admin = User::factory()->admin()->create();
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)->patch(route('admin.settings.listing-moderation'), ['mode' => 'all'])->assertForbidden();
    expect(ListingModeration::mode())->toBe(ListingModeration::REVIEW_FLAGGED);

    $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('Listing moderation')->assertSee('Publish at once, review flagged');
    $this->actingAs($admin)->patch(route('admin.settings.listing-moderation'), ['mode' => 'all'])->assertRedirect();
    expect(ListingModeration::mode())->toBe(ListingModeration::REVIEW_ALL);
    $this->actingAs($admin)->patch(route('admin.settings.listing-moderation'), ['mode' => 'nonsense'])->assertSessionHasErrors('mode');
});

it('lists the newest listings first for admins', function () {
    $admin = User::factory()->admin()->create();
    Property::factory()->create(['title' => 'Older Listing In Gulberg', 'created_at' => now()->subDays(5)]);
    Property::factory()->create(['title' => 'Newest Listing In Gulberg', 'created_at' => now()]);

    $this->actingAs($admin)->get(route('admin.properties.index', ['status' => 'published']))
        ->assertOk()
        ->assertSeeInOrder(['Newest Listing In Gulberg', 'Older Listing In Gulberg']);
});

it('keeps the All tab on the next page of the admin list', function () {
    $admin = User::factory()->admin()->create();
    Property::factory()->count(21)->create();

    foreach (['', 'all'] as $value) {
        $this->actingAs($admin)->get(route('admin.properties.index', ['status' => $value]))
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('status=all', false);
    }
    $this->actingAs($admin)->get(route('admin.properties.index', ['status' => 'all', 'page' => 2]))
        ->assertOk()
        ->assertViewHas('status', null)
        ->assertViewHas('properties', fn ($p) => $p->count() === 1);
});

it('lists the last day\'s new live listings for admins to skim', function () {
    $admin = User::factory()->admin()->create();
    Property::factory()->create(['title' => 'Fresh Live House In Gulberg', 'published_at' => now()->subHours(2)]);
    Property::factory()->create(['title' => 'Older Live House In Gulberg', 'published_at' => now()->subDays(3)]);

    $this->actingAs($admin)->get(route('admin.properties.index', ['flag' => 'new']))
        ->assertOk()
        ->assertSee('New live, last 24 h')
        ->assertSee('Fresh Live House In Gulberg')
        ->assertDontSee('Older Live House In Gulberg');
});
