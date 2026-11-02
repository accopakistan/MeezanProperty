<?php

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Enums\PropertyStatus;
use App\Models\BuyerRequirement;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Property;
use App\Models\User;
use App\Notifications\BillingAdminAlert;
use App\Notifications\BillingUpdated;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionManager;
use App\Services\Leads\LeadRouter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function limitPackage(array $attributes = []): Package
{
    $package = new Package($attributes + ['name' => 'Plan '.Str::random(6), 'price' => 0, 'currency' => 'PKR', 'billing_interval' => 'month', 'is_active' => true]);
    $package->save();

    return $package;
}

function gulbergRequirement(): BuyerRequirement
{
    $requirement = new BuyerRequirement(['purpose' => 'sale', 'city' => 'Lahore', 'area_ids' => [area('Gulberg')->id]]);
    $requirement->user_id = User::factory()->create()->id;
    $requirement->save();

    return $requirement;
}

/*
| Listing limits
*/

it('counts only live and in-review listings against the allowance', function () {
    $dealer = User::factory()->dealer()->create();
    foreach ([PropertyStatus::Published, PropertyStatus::Pending, PropertyStatus::Draft, PropertyStatus::Rejected, PropertyStatus::ChangesRequested, PropertyStatus::Expired, PropertyStatus::Archived, PropertyStatus::Suspended] as $status) {
        Property::factory()->forDealer($dealer)->status($status)->create();
    }

    expect((new Entitlements)->usage($dealer)['listings'])->toBe(2);
});

it('blocks submitting a listing over the limit with a clear message and keeps the draft', function () {
    Notification::fake();
    limitPackage(['is_default' => true, 'listing_limit' => 1]);
    $dealer = User::factory()->dealer()->create();
    Property::factory()->forDealer($dealer)->status(PropertyStatus::Published)->create();

    $this->actingAs($dealer)->get(route('dealer.properties.create'))->assertOk()
        ->assertSee('You have reached your listing limit. Upgrade your package to add more listings.')
        ->assertSee('1 of 1 active listings')->assertSee(route('dealer.billing.packages'));

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['action' => 'submit']))
        ->assertRedirect()
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'You have reached your listing limit. Upgrade your package to add more listings.') && str_contains($message, '(1 of 1 used)'));

    $draft = Property::where('user_id', $dealer->id)->latest('id')->first();
    expect($draft->status)->toBe(PropertyStatus::Draft)->and(Property::where('user_id', $dealer->id)->count())->toBe(2);
    Notification::assertSentTo($dealer, BillingUpdated::class, fn ($n) => $n->event === 'listing_limit_reached');

    // Under the limit again (one archived), the same draft can be submitted.
    Property::where('user_id', $dealer->id)->where('status', 'published')->update(['status' => 'archived']);
    $this->actingAs($dealer)->post(route('dealer.properties.submit', $draft))->assertRedirect(route('dealer.properties.index'));
    expect($draft->fresh()->status)->toBe(PropertyStatus::Pending);
});

it('lets dealers keep editing live listings when at the limit', function () {
    limitPackage(['is_default' => true, 'listing_limit' => 1]);
    $dealer = User::factory()->dealer()->create();
    $live = Property::factory()->forDealer($dealer)->status(PropertyStatus::Published)->create();

    $this->actingAs($dealer)->put(route('dealer.properties.update', $live), listingPayload(['title' => 'Updated title for this live listing', 'action' => 'draft']))->assertRedirect();
    expect($live->fresh())->status->toBe(PropertyStatus::Pending)->title->toBe('Updated title for this live listing');
});

it('blocks renewing an expired listing when the allowance is used up', function () {
    limitPackage(['is_default' => true, 'listing_limit' => 1]);
    $dealer = User::factory()->dealer()->create();
    Property::factory()->forDealer($dealer)->status(PropertyStatus::Published)->create();
    $expired = Property::factory()->forDealer($dealer)->status(PropertyStatus::Expired)->create(['expires_at' => now()->subDays(2)]);

    $this->actingAs($dealer)->post(route('dealer.properties.renew', $expired), ['still_available' => '1'])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'You have reached your listing limit'));
    expect($expired->fresh()->status)->toBe(PropertyStatus::Expired);
});

it('never removes listings when a dealer is over a smaller plan, but blocks new ones', function () {
    $dealer = User::factory()->dealer()->create();
    $listings = Property::factory()->count(3)->forDealer($dealer)->status(PropertyStatus::Published)->create();
    limitPackage(['is_default' => true, 'listing_limit' => 1]);
    Artisan::call('meezan:billing-lifecycle');

    expect(Property::whereIn('id', $listings->pluck('id'))->where('status', 'published')->count())->toBe(3)
        ->and((new Entitlements)->isOverLimit($dealer, 'listings'))->toBeTrue();

    $this->actingAs($dealer)->get(route('dealer.subscription'))->assertOk()->assertSee('You are over your package limits')->assertSee('Nothing has been removed');
    $draft = Property::factory()->forDealer($dealer)->status(PropertyStatus::Draft)->create();
    $this->actingAs($dealer)->post(route('dealer.properties.submit', $draft))->assertSessionHas('error');
    expect($draft->fresh()->status)->toBe(PropertyStatus::Draft);
});

it('can require dealers to be within the new limit before moving to a smaller package', function () {
    config(['meezan.billing.over_limit_policy' => 'require_within_limit']);
    $dealer = User::factory()->dealer()->create();
    Property::factory()->count(3)->forDealer($dealer)->status(PropertyStatus::Published)->create();
    $small = limitPackage(['name' => 'Small', 'listing_limit' => 1]);

    $this->actingAs($dealer)->post(route('dealer.billing.choose', $small))
        ->assertSessionHasErrors(['package' => 'Small allows 1 active listings and you have 3. Archive 2 listing(s) first.']);
});

/*
| Lead limits
*/

it('skips dealers at their monthly lead limit, holds the lead when all are full, and retries next month', function () {
    Notification::fake();
    config(['meezan.leads.max_dealers_per_requirement' => 1]);
    $admin = User::factory()->admin()->create();
    limitPackage(['is_default' => true, 'lead_limit' => 1]);
    $a = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    $b = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    $router = app(LeadRouter::class);

    $first = $router->routeRequirement(gulbergRequirement(), true);
    $second = $router->routeRequirement(gulbergRequirement(), true);
    expect($first->activeAssignments()->sole()->dealer_id)->not->toBe($second->activeAssignments()->sole()->dealer_id);

    $held = $router->routeRequirement(gulbergRequirement(), true);
    expect($held->status)->toBe(LeadStatus::Unrouted)->and($held->hold_reason)->toBe('capacity')->and($held->assignments()->count())->toBe(0);
    Notification::assertSentTo($admin, BillingAdminAlert::class, fn ($n) => $n->event === 'leads_held');
    // The top-ranked dealer missed out because of their limit and is told (once a day).
    $topRanked = $first->activeAssignments()->sole()->dealer_id === $a->id ? $a : $b;
    Notification::assertSentToTimes($topRanked, BillingUpdated::class, 1);
    Notification::assertSentTo($topRanked, BillingUpdated::class, fn ($n) => $n->event === 'lead_limit_reached');

    $this->actingAs($admin)->get(route('admin.leads.show', $held))->assertSee('Held for staff')->assertSee('at limit');

    // Allowances reset monthly: the daily job routes the held lead.
    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addHour());
    Artisan::call('meezan:billing-lifecycle');
    expect($held->fresh())->status->toBe(LeadStatus::Assigned)->hold_reason->toBeNull();
});

it('can assign over the limit when configured, saying so', function () {
    config(['meezan.billing.lead_overflow' => 'allow_over_limit', 'meezan.leads.max_dealers_per_requirement' => 1]);
    limitPackage(['is_default' => true, 'lead_limit' => 0]);
    User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();

    $lead = app(LeadRouter::class)->routeRequirement(gulbergRequirement(), true);
    expect($lead->status)->toBe(LeadStatus::Assigned)->and($lead->activeAssignments()->sole()->reason)->toContain('over the monthly lead limit');
});

it('does not count spam, duplicates, reassigned leads or listing enquiries against the allowance', function () {
    $admin = User::factory()->admin()->create();
    limitPackage(['is_default' => true, 'lead_limit' => 1]);
    $dealer = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    $router = app(LeadRouter::class);

    $lead = $router->routeRequirement(gulbergRequirement(), true);
    expect((new Entitlements)->usage($dealer)['leads'])->toBe(1);

    $this->actingAs($admin)->patch(route('admin.leads.validity', $lead), ['invalid_reason' => 'spam'])->assertRedirect();
    expect($lead->fresh()->isInvalid())->toBeTrue()->and((new Entitlements)->usage($dealer)['leads'])->toBe(0);

    $property = Property::factory()->forDealer($dealer)->status(PropertyStatus::Published)->create();
    $inquiry = new Inquiry(['name' => 'Buyer', 'email' => 'b@example.com', 'phone' => '03001112222', 'message' => 'Interested in this house.']);
    $inquiry->forceFill(['property_id' => $property->id, 'recipient_id' => $dealer->id])->save();
    $router->routeInquiry($inquiry);
    expect((new Entitlements)->usage($dealer)['leads'])->toBe(0);

    $next = $router->routeRequirement(gulbergRequirement(), true);
    expect($next->status)->toBe(LeadStatus::Assigned);
    $other = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    $router->reassign($next, $other, $admin, 'Testing');
    expect((new Entitlements)->usage($dealer)['leads'])->toBe(0);

    $this->actingAs($admin)->patch(route('admin.leads.validity', $lead), ['invalid_reason' => ''])->assertRedirect();
    expect($lead->fresh()->isInvalid())->toBeFalse();
});

it('ranks dealers whose package includes priority leads higher', function () {
    config(['meezan.leads.max_dealers_per_requirement' => 1]);
    User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    $priority = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    app(SubscriptionManager::class)->choose($priority, limitPackage(['name' => 'Priority', 'features' => ['priority_leads']]));

    $lead = app(LeadRouter::class)->routeRequirement(gulbergRequirement(), true);
    expect($lead->activeAssignments()->sole())->dealer_id->toBe($priority->id)->reason->toContain('priority package');
});

/*
| Featured listings
*/

it('lets dealers feature listings only within their package allowance', function () {
    $dealer = User::factory()->dealer()->create();
    [$one, $two] = Property::factory()->count(2)->forDealer($dealer)->status(PropertyStatus::Published)->create();

    $this->actingAs($dealer)->post(route('dealer.properties.feature', $one))->assertSessionHas('error', fn ($m) => str_contains($m, 'not included in your package'));
    expect($one->fresh()->is_featured)->toBeFalse();

    app(SubscriptionManager::class)->choose($dealer, limitPackage(['features' => ['featured_listings'], 'featured_limit' => 1]));
    $this->actingAs($dealer)->post(route('dealer.properties.feature', $one))->assertSessionHas('status');
    $this->actingAs($dealer)->post(route('dealer.properties.feature', $two))->assertSessionHas('error', fn ($m) => str_contains($m, 'You have used all 1 featured listings'));
    $this->actingAs($dealer)->post(route('dealer.properties.feature', $one));

    expect($one->fresh()->is_featured)->toBeFalse()->and($two->fresh()->is_featured)->toBeFalse();
    $this->actingAs(User::factory()->dealer()->create())->post(route('dealer.properties.feature', $two))->assertForbidden();
});

/*
| Existing data
*/

it('leaves existing dealers, listings, verification and leads untouched when billing runs', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    Property::factory()->count(4)->forDealer($dealer)->status(PropertyStatus::Published)->create();
    $lead = app(LeadRouter::class)->routeRequirement(gulbergRequirement(), true);
    $before = [User::count(), Property::count(), Property::where('status', 'published')->count(), Lead::count()];

    limitPackage(['is_default' => true, 'listing_limit' => 1, 'lead_limit' => 0]);
    Artisan::call('meezan:billing-lifecycle');
    $this->travel(40)->days();
    Artisan::call('meezan:billing-lifecycle');

    expect([User::count(), Property::count(), Property::where('status', 'published')->count(), Lead::count()])->toBe($before)
        ->and($dealer->dealerProfile->fresh()->verification_status)->toBe(DealerStatus::Verified)
        ->and($lead->fresh()->status)->toBe(LeadStatus::Assigned);
});
