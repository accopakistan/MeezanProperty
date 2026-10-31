<?php

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ListingExpiring;
use Illuminate\Support\Facades\Notification;

it('expires lapsed listings, reminds dealers once before expiry and leaves others alone', function () {
    Notification::fake();
    $lapsed = Property::factory()->create(['expires_at' => now()->subDay()]);
    $soon = Property::factory()->create(['expires_at' => now()->addDays(5)]);
    $later = Property::factory()->create(['expires_at' => now()->addDays(40)]);
    $draft = Property::factory()->status(PropertyStatus::Draft)->create(['expires_at' => now()->subDay()]);

    $this->artisan('meezan:listing-lifecycle')->assertSuccessful();

    expect($lapsed->fresh()->status)->toBe(PropertyStatus::Expired)
        ->and($soon->fresh()->status)->toBe(PropertyStatus::Published)
        ->and($soon->fresh()->expiry_reminder_sent_at)->not->toBeNull()
        ->and($later->fresh()->expiry_reminder_sent_at)->toBeNull()
        ->and($draft->fresh()->status)->toBe(PropertyStatus::Draft);
    Notification::assertSentTo($lapsed->dealer, ListingExpiring::class, fn ($n) => $n->expired);
    Notification::assertSentTo($soon->dealer, ListingExpiring::class, fn ($n) => ! $n->expired);
    Notification::assertNotSentTo($later->dealer, ListingExpiring::class);

    // Running again sends nothing new.
    $this->artisan('meezan:listing-lifecycle')->assertSuccessful();
    Notification::assertSentToTimes($soon->dealer, ListingExpiring::class, 1);
    Notification::assertSentToTimes($lapsed->dealer, ListingExpiring::class, 1);
});

it('changes nothing in a dry run', function () {
    Notification::fake();
    $lapsed = Property::factory()->create(['expires_at' => now()->subDay()]);

    $this->artisan('meezan:listing-lifecycle', ['--dry-run' => true])
        ->expectsOutputToContain('[dry run] Expire '.$lapsed->reference)
        ->assertSuccessful();

    expect($lapsed->fresh()->status)->toBe(PropertyStatus::Published);
    Notification::assertNothingSent();
});

it('renews a listing close to expiry for another full period without a new review', function () {
    $dealer = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($dealer)->create(['expires_at' => now()->addDays(3), 'expiry_reminder_sent_at' => now()]);

    $this->actingAs($dealer)->post(route('dealer.properties.renew', $property), ['still_available' => '1'])->assertSessionHas('status');

    $fresh = $property->fresh();
    expect($fresh->status)->toBe(PropertyStatus::Published)
        ->and($fresh->expires_at->isSameDay(now()->addDays(90)))->toBeTrue()
        ->and($fresh->expiry_reminder_sent_at)->toBeNull()
        ->and($fresh->published_at->equalTo($property->published_at))->toBeTrue();
});

it('puts a recently expired listing back online when renewed', function () {
    $dealer = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($dealer)->status(PropertyStatus::Expired)->create(['expires_at' => now()->subDays(10)]);

    $this->actingAs($dealer)->post(route('dealer.properties.renew', $property), ['still_available' => '1']);

    expect($property->fresh())->status->toBe(PropertyStatus::Published)
        ->and($property->fresh()->isPublic())->toBeTrue();
});

it('also renews a listing that lapsed before the daily job ran', function () {
    $dealer = User::factory()->dealer()->create();
    $property = Property::factory()->forDealer($dealer)->create(['expires_at' => now()->subDay()]);
    expect($property->isPublic())->toBeFalse()->and($property->canBeRenewed())->toBeTrue();

    $this->actingAs($dealer)->post(route('dealer.properties.renew', $property), ['still_available' => '1']);

    expect($property->fresh()->isPublic())->toBeTrue();
});

it('refuses renewal too early, too late, without confirmation or for someone else’s listing', function () {
    $dealer = User::factory()->dealer()->create();
    $early = Property::factory()->forDealer($dealer)->create(['expires_at' => now()->addDays(60)]);
    $late = Property::factory()->forDealer($dealer)->status(PropertyStatus::Expired)->create(['expires_at' => now()->subDays(45)]);
    $soon = Property::factory()->forDealer($dealer)->create(['expires_at' => now()->addDays(3)]);

    $this->actingAs($dealer)->post(route('dealer.properties.renew', $early), ['still_available' => '1'])->assertSessionHas('error');
    $this->actingAs($dealer)->post(route('dealer.properties.renew', $late), ['still_available' => '1'])->assertSessionHas('error');
    $this->actingAs($dealer)->post(route('dealer.properties.renew', $soon))->assertSessionHasErrors('still_available');
    $this->actingAs(User::factory()->dealer()->create())->post(route('dealer.properties.renew', $soon), ['still_available' => '1'])->assertForbidden();

    expect($early->fresh()->expires_at->isSameDay(now()->addDays(60)))->toBeTrue()
        ->and($late->fresh()->status)->toBe(PropertyStatus::Expired)
        ->and($soon->fresh()->expires_at->isSameDay(now()->addDays(3)))->toBeTrue();
});

it('lets a long-expired listing be resubmitted for review instead', function () {
    $dealer = User::factory()->dealer()->create();
    $late = Property::factory()->forDealer($dealer)->status(PropertyStatus::Expired)->create(['expires_at' => now()->subDays(45)]);
    $lapsedPublished = Property::factory()->forDealer($dealer)->create(['expires_at' => now()->subDays(45)]);

    $this->actingAs($dealer)->post(route('dealer.properties.submit', $late))->assertRedirect();
    $this->actingAs($dealer)->post(route('dealer.properties.submit', $lapsedPublished))->assertRedirect();

    expect($late->fresh()->status)->toBe(PropertyStatus::Pending)
        ->and($lapsedPublished->fresh()->status)->toBe(PropertyStatus::Pending);
});

it('shows expiry dates and the right action on My Listings', function () {
    $dealer = User::factory()->dealer()->create();
    Property::factory()->forDealer($dealer)->create(['title' => 'Expiring Soon House', 'expires_at' => now()->addDays(3)]);
    Property::factory()->forDealer($dealer)->status(PropertyStatus::Expired)->create(['title' => 'Long Gone House', 'expires_at' => now()->subDays(45)]);

    $this->actingAs($dealer)->get(route('dealer.properties.index'))
        ->assertOk()
        ->assertSee('Live until '.now()->addDays(3)->format('j M Y'))
        ->assertSee('Renew')
        ->assertSee('Expired '.now()->subDays(45)->format('j M Y'))
        ->assertSee('Submit');
});

it('resets the reminder when an admin approves a listing for a new period', function () {
    $property = Property::factory()->status(PropertyStatus::Pending)->create(['expiry_reminder_sent_at' => now()->subMonth()]);

    $this->actingAs(User::factory()->admin()->create())->patch(route('admin.properties.moderate', $property), ['decision' => 'approve']);

    expect($property->fresh()->expiry_reminder_sent_at)->toBeNull();
});
