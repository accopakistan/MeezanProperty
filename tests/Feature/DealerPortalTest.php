<?php

use App\Enums\PropertyStatus;
use App\Models\Inquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('keeps the dealer portal away from guests and buyers', function () {
    $this->get('/dealer/dashboard')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/dealer/dashboard')->assertForbidden();
});

it('shows the dealer dashboard with real counts', function () {
    $dealer = User::factory()->dealer()->create();
    Property::factory()->forDealer($dealer)->count(2)->create();
    Property::factory()->forDealer($dealer)->status(PropertyStatus::Pending)->create();

    $this->actingAs($dealer)->get('/dealer/dashboard')
        ->assertOk()
        ->assertSee('Verification pending')
        ->assertSeeInOrder(['2', 'Active Listings', '1 pending review']);
});

it('creates a draft listing with photos', function () {
    Storage::fake('public');
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)
        ->post(route('dealer.properties.store'), listingPayload([
            'action' => 'draft',
            'images' => [UploadedFile::fake()->image('front.jpg', 1200, 900), UploadedFile::fake()->image('lounge.png', 800, 600)],
        ]))
        ->assertRedirect();

    $property = Property::sole();
    expect($property->status)->toBe(PropertyStatus::Draft)
        ->and($property->user_id)->toBe($dealer->id)
        ->and($property->area_sqft)->toBe(2250)
        ->and($property->reference)->toBe('MP-'.str_pad($property->id, 6, '0', STR_PAD_LEFT))
        ->and($property->images)->toHaveCount(2);

    Storage::disk('public')->assertExists($property->images->first()->path);
});

it('submits a listing for review instead of publishing it', function () {
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['action' => 'submit']));

    expect(Property::sole()->status)->toBe(PropertyStatus::Pending);
    $this->get('/properties')->assertDontSee('10 Marla House with Basement in Gulberg');
});

it('validates listings and uploads', function () {
    Storage::fake('public');
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)
        ->post(route('dealer.properties.store'), listingPayload([
            'title' => 'Short',
            'price' => 5,
            'bedrooms' => null,
            'description' => 'Too short',
            'area_id' => 999999,
            'images' => [UploadedFile::fake()->create('virus.php', 10, 'application/x-php'), UploadedFile::fake()->image('tiny.jpg', 100, 100)],
        ]))
        ->assertSessionHasErrors(['title', 'price', 'bedrooms', 'description', 'area_id', 'images.0', 'images.1']);

    expect(Property::count())->toBe(0);
});

it('does not require bedrooms for plots', function () {
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)
        ->post(route('dealer.properties.store'), listingPayload(['type' => 'residential_plot', 'bedrooms' => null, 'title' => '10 Marla Residential Plot in Gulberg']))
        ->assertSessionHasNoErrors();

    expect(Property::sole()->bedrooms)->toBeNull();
});

it('prevents dealers from touching another dealer’s listing', function () {
    $owner = User::factory()->dealer()->create();
    $other = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($owner)->create();

    $this->actingAs($other)->get(route('dealer.properties.edit', $property))->assertForbidden();
    $this->actingAs($other)->put(route('dealer.properties.update', $property), listingPayload())->assertForbidden();
    $this->actingAs($other)->delete(route('dealer.properties.destroy', $property))->assertForbidden();
    $this->actingAs($other)->post(route('dealer.properties.submit', $property))->assertForbidden();

    expect($property->fresh()->title)->not->toBe(listingPayload()['title']);
});

it('sends edited published listings back to review', function () {
    $dealer = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($dealer)->create();

    $this->actingAs($dealer)->put(route('dealer.properties.update', $property), listingPayload(['price' => 60000000]))->assertRedirect();

    expect($property->fresh())->status->toBe(PropertyStatus::Pending)->price->toBe(60000000);
});

it('cannot mass-assign moderation fields', function () {
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['status' => 'published', 'is_featured' => 1, 'user_id' => 999]));

    expect(Property::sole())->status->toBe(PropertyStatus::Draft)->is_featured->toBeFalse()->user_id->toBe($dealer->id);
});

it('shows a dealer only their own inquiries and lets them update status', function () {
    $dealer = User::factory()->dealer()->create();
    $other = User::factory()->dealer()->create();
    $mine = Property::factory()->forDealer($dealer)->create();

    $inquiry = new Inquiry(['name' => 'Buyer One', 'email' => 'b1@example.com', 'phone' => '03001112222', 'message' => 'Interested in viewing.']);
    $inquiry->forceFill(['property_id' => $mine->id, 'recipient_id' => $dealer->id])->save();

    $this->actingAs($dealer)->get('/dealer/leads')->assertOk()->assertSee('Buyer One');
    $this->actingAs($other)->get('/dealer/leads')->assertOk()->assertDontSee('Buyer One');

    $this->actingAs($other)->patch(route('dealer.inquiries.update', $inquiry), ['status' => 'closed'])->assertForbidden();
    $this->actingAs($dealer)->patch(route('dealer.inquiries.update', $inquiry), ['status' => 'contacted'])->assertRedirect();

    expect($inquiry->fresh()->status)->toBe('contacted');
});

it('requires an ownership or authorisation declaration and records it', function () {
    $dealer = User::factory()->dealer()->create();

    $this->actingAs($dealer)
        ->post(route('dealer.properties.store'), listingPayload(['authorization_type' => null, 'authorization_confirmed' => null]))
        ->assertSessionHasErrors(['authorization_type', 'authorization_confirmed']);

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['authorization_type' => 'owner', 'covered_area_sqft' => 3200, 'completion_status' => 'ready']));

    expect(Property::sole())
        ->authorization_type->toBe('owner')
        ->authorization_confirmed_at->not->toBeNull()
        ->covered_area_sqft->toBe(3200)
        ->completion_status->toBe('ready');
});

it('warns the dealer when a submitted listing looks like a duplicate', function () {
    $dealer = User::factory()->dealer()->create();
    Property::factory()->create(['area_id' => area('Gulberg')->id, 'price' => 55000000, 'area_size' => 10, 'area_unit' => 'marla']);

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['action' => 'submit', 'price' => 56000000]))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'looks similar'));
});

it('records asking-price changes only for listings that have been public', function () {
    $dealer = User::factory()->dealer()->create();
    $live = Property::factory()->forDealer($dealer)->create(['price' => 50000000]);
    $draft = Property::factory()->forDealer($dealer)->status(PropertyStatus::Draft)->create(['price' => 50000000, 'published_at' => null]);

    $live->update(['price' => 47500000]);
    $draft->update(['price' => 45000000]);

    expect($live->priceChanges()->sole())->old_price->toBe(50000000)->new_price->toBe(47500000)
        ->and($draft->priceChanges()->count())->toBe(0);

    $this->get(route('properties.show', $live))->assertSee('Asking Price History')->assertSee('PKR 4.75 Crore');
});
