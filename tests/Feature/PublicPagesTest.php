<?php

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;

it('renders the public pages', function (string $uri) {
    Property::factory()->count(3)->create();

    $this->get($uri)->assertOk();
})->with([
    '/', '/buy', '/rent', '/plots', '/commercial', '/properties', '/map',
    '/ai-property-finder', '/about', '/how-it-works', '/contact', '/terms', '/privacy',
    '/login', '/register', '/dealer/register', '/forgot-password',
]);

it('shows a useful empty state when there are no listings', function () {
    $this->get('/')->assertOk()->assertSee('Listings are on their way');
    $this->get('/properties')->assertOk()->assertSee('No listings match these filters');
});

it('shows a published property with its details', function () {
    $property = Property::factory()->create(['title' => '10 Marla House in Test Block', 'price' => 45_000_000]);

    $this->get(route('properties.show', $property))
        ->assertOk()
        ->assertSee('10 Marla House in Test Block')
        ->assertSee('PKR 4.5 Crore')
        ->assertSee($property->reference)
        ->assertSee('Verification pending');
});

it('hides unpublished listings from the public but lets the owner preview', function () {
    $property = Property::factory()->status(PropertyStatus::Pending)->create();

    $this->get(route('properties.show', $property))->assertNotFound();

    $this->actingAs($property->dealer)
        ->get(route('properties.show', $property))
        ->assertOk()
        ->assertSee('Preview');

    $this->actingAs(User::factory()->create())
        ->get(route('properties.show', $property))
        ->assertNotFound();
});

it('does not show expired listings', function () {
    $property = Property::factory()->create(['expires_at' => now()->subDay()]);

    $this->get(route('properties.show', $property))->assertNotFound();
});

it('only shows dealer contact details for verified dealers', function () {
    $dealer = User::factory()->dealer()->create();
    $dealer->dealerProfile->forceFill(['public_phone' => '042 3576 0000', 'whatsapp_number' => '923001112222'])->save();
    $property = Property::factory()->forDealer($dealer)->create();

    $this->get(route('properties.show', $property))->assertDontSee('wa.me/923001112222')->assertDontSee('Call Now');

    $dealer->dealerProfile->forceFill(['verification_status' => 'verified'])->save();

    $this->get(route('properties.show', $property))->assertSee('wa.me/923001112222')->assertSee('Call Now');
});

it('lists published non-demo listings in the sitemap', function () {
    $real = Property::factory()->create();
    $demo = Property::factory()->create();
    $demo->forceFill(['is_demo' => true])->save();
    $draft = Property::factory()->status(PropertyStatus::Draft)->create();

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(route('properties.show', $real))
        ->assertDontSee(route('properties.show', $demo))
        ->assertDontSee(route('properties.show', $draft));
});
