<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\BuyerRequirement;
use App\Models\DealerProfile;
use App\Models\ListingReport;
use App\Models\Project;
use App\Models\Property;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\SavedSearchMatches;
use App\Services\Ai\LlmRequirementParser;
use App\Services\Ai\RulesRequirementParser;
use App\Services\Leads\LeadRouter;
use App\Services\PropertySearch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/* Saved searches and alerts ------------------------------------------- */

it('saves a search with only known filters and shows new matches as alerts', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->post(route('account.saved-searches.store'), [
        'filters' => json_encode(['purpose' => 'sale', 'area' => area('Gulberg')->slug, 'evil' => 'x']),
    ])->assertRedirect();

    $search = SavedSearch::sole();
    expect($search->filters)->toBe(['purpose' => 'sale', 'area' => area('Gulberg')->slug])
        ->and($search->name)->toBe('Properties for Sale in Gulberg, Lahore');

    $this->travel(1)->minute();
    Property::factory()->create(['title' => 'Fresh Gulberg Listing', 'area_id' => area('Gulberg')->id, 'published_at' => now()]);
    Property::factory()->create(['title' => 'Fresh Johar Listing', 'area_id' => area('Johar Town')->id, 'published_at' => now()]);

    $this->actingAs($buyer)->get(route('account.alerts'))->assertSee('Fresh Gulberg Listing')->assertDontSee('Fresh Johar Listing');
    expect($buyer->fresh()->unseenAlertCount())->toBe(1);

    $this->actingAs($buyer)->get(route('account.saved-searches.open', $search))->assertRedirect($search->url());
    expect($buyer->fresh()->unseenAlertCount())->toBe(0);
});

it('keeps saved searches private', function () {
    $owner = User::factory()->create();
    $search = new SavedSearch(['name' => 'Mine', 'filters' => []]);
    $search->user_id = $owner->id;
    $search->save();

    $this->actingAs(User::factory()->create())->get(route('account.saved-searches.open', $search))->assertNotFound();
    $this->actingAs(User::factory()->create())->delete(route('account.saved-searches.destroy', $search))->assertNotFound();
    auth()->logout();
    $this->post(route('account.saved-searches.store'), ['filters' => '{}'])->assertRedirect(route('login'));
});

it('only emails alerts with a real mail provider and the buyer’s consent', function () {
    Notification::fake();
    $optedIn = User::factory()->create(['notify_email' => true]);
    $optedOut = User::factory()->create(['notify_email' => false]);

    foreach ([$optedIn, $optedOut] as $user) {
        $s = new SavedSearch(['name' => 'Gulberg', 'filters' => ['area' => area('Gulberg')->slug], 'alert_frequency' => 'daily']);
        $s->user_id = $user->id;
        $s->save();
    }
    $this->travel(1)->minute();
    Property::factory()->create(['area_id' => area('Gulberg')->id, 'published_at' => now()]);

    config(['mail.default' => 'log']);
    $this->artisan('meezan:send-search-alerts')->assertSuccessful();
    Notification::assertNothingSent();

    config(['mail.default' => 'smtp']);
    $this->artisan('meezan:send-search-alerts')->assertSuccessful();
    Notification::assertSentTo($optedIn, SavedSearchMatches::class);
    Notification::assertNotSentTo($optedOut, SavedSearchMatches::class);
});

it('updates notification preferences', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->put(route('account.notifications.update'), ['notify_email' => '1'])->assertRedirect();

    expect($buyer->fresh())->notify_email->toBeTrue()->notify_in_app->toBeFalse()->notify_whatsapp->toBeFalse();
});

/* Compare ------------------------------------------------------------- */

it('compares up to four published listings', function () {
    $listings = Property::factory()->count(5)->create(['covered_area_sqft' => 2400]);

    foreach ($listings->take(4) as $p) {
        $this->post(route('compare.toggle', $p));
    }
    $this->post(route('compare.toggle', $listings[4]))->assertSessionHas('error');

    $this->get(route('compare'))->assertOk()->assertSee($listings[0]->title)->assertSee('2,400 Sq. Ft.')->assertDontSee($listings[4]->title);

    $draft = Property::factory()->status(PropertyStatus::Draft)->create();
    $this->post(route('compare.clear'));
    $this->post(route('compare.toggle', $draft))->assertNotFound();
});

/* Search: viewport, covered area, status, features --------------------- */

it('filters by map viewport and never invents locations', function () {
    $inside = Property::factory()->create(['title' => 'Inside Box', 'latitude' => 31.52, 'longitude' => 74.35]);
    $outside = Property::factory()->create(['title' => 'Outside Box', 'latitude' => 31.30, 'longitude' => 74.10]);
    $noCoords = Property::factory()->create(['title' => 'No Coordinates']);

    $this->get('/properties?north=31.6&south=31.4&east=74.5&west=74.2')
        ->assertSee('Inside Box')->assertDontSee('Outside Box')->assertDontSee('No Coordinates');

    // Incomplete bounds are ignored, so every listing stays discoverable.
    $this->get('/properties?north=31.6&south=31.4')->assertSee('No Coordinates')->assertSee('Outside Box');
});

it('filters by covered area, completion status and features', function () {
    Property::factory()->create(['title' => 'Big Ready Corner', 'covered_area_sqft' => 4000, 'completion_status' => 'ready', 'amenities' => ['corner', 'parking']]);
    Property::factory()->create(['title' => 'Small Grey Structure', 'covered_area_sqft' => 1200, 'completion_status' => 'under_construction', 'amenities' => ['parking']]);

    $this->get('/properties?min_covered=3000')->assertSee('Big Ready Corner')->assertDontSee('Small Grey Structure');
    $this->get('/properties?completion=under_construction')->assertSee('Small Grey Structure')->assertDontSee('Big Ready Corner');
    $this->get('/properties?features[]=corner')->assertSee('Big Ready Corner')->assertDontSee('Small Grey Structure');
    $this->get('/properties?features[]=parking&features[]=corner')->assertSee('Big Ready Corner')->assertDontSee('Small Grey Structure');
});

/* Reports and moderation ---------------------------------------------- */

it('accepts one report per visitor and lets admins unpublish with a note', function () {
    $property = Property::factory()->create();

    $this->post(route('properties.report', $property), ['reason' => 'fraud', 'details' => 'Asked for advance'])->assertRedirect();
    $this->post(route('properties.report', $property), ['reason' => 'fraud']);
    expect(ListingReport::count())->toBe(1);

    $admin = User::factory()->admin()->create();
    $report = ListingReport::sole();
    $this->actingAs($admin)->get(route('admin.reports.index'))->assertSee('Suspected fraud or scam');
    $this->actingAs($admin)->patch(route('admin.reports.resolve', $report), ['decision' => 'unpublish'])->assertSessionHasErrors('note');
    $this->actingAs($admin)->patch(route('admin.reports.resolve', $report), ['decision' => 'unpublish', 'note' => 'Confirmed scam'])->assertRedirect();

    expect($report->fresh()->status)->toBe('resolved')->and($property->fresh()->status)->toBe(PropertyStatus::Draft);
    auth()->logout();
    $this->get(route('properties.show', $property))->assertNotFound();
});

it('lists likely duplicates for review', function () {
    $admin = User::factory()->admin()->create();
    $live = Property::factory()->create(['area_id' => area('Gulberg')->id, 'price' => 40_000_000]);
    $copy = Property::factory()->status(PropertyStatus::Pending)->create(['area_id' => area('Gulberg')->id, 'price' => 41_000_000]);
    Property::factory()->status(PropertyStatus::Pending)->create(['area_id' => area('Gulberg')->id, 'price' => 90_000_000]);

    $this->actingAs($admin)->get(route('admin.properties.duplicates'))->assertSee($copy->reference)->assertSee($live->reference);
});

/* Sell journey, owners and dealer directory ---------------------------- */

it('registers a private owner who can list but is not a dealer', function () {
    $this->post(route('sell.owner.store'), [
        'name' => 'Nadia Owner', 'email' => 'nadia@example.com', 'phone' => '0300 1112233',
        'password' => 'secret-password', 'password_confirmation' => 'secret-password', 'city' => 'Lahore', 'terms' => '1',
    ])->assertRedirect(route('dealer.properties.create'));

    $profile = DealerProfile::sole();
    expect($profile->business_type)->toBe('owner')->and($profile->accepts_leads)->toBeFalse();

    $this->get(route('dealers.index'))->assertDontSee('Nadia Owner');
    $this->get('/sell')->assertOk()->assertSee('I’m the owner', false);
});

it('shows only public dealers in the directory', function () {
    $verified = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    $withListing = User::factory()->dealer()->create();
    Property::factory()->forDealer($withListing)->create();
    $hidden = User::factory()->dealer()->create();
    $rejected = User::factory()->dealer(DealerStatus::Rejected)->create();
    $hidden->dealerProfile->forceFill(['public_phone' => '042 1111111'])->save();

    $this->get(route('dealers.index'))
        ->assertSee($verified->dealerProfile->agency_name)
        ->assertSee($withListing->dealerProfile->agency_name)
        ->assertDontSee($hidden->dealerProfile->agency_name)
        ->assertDontSee($rejected->dealerProfile->agency_name);

    $this->get(route('dealers.index', ['area' => area('Gulberg')->slug]))->assertSee($verified->dealerProfile->agency_name)->assertDontSee($withListing->dealerProfile->agency_name);
    $this->get(route('dealers.show', $hidden->dealerProfile))->assertNotFound();
    $this->get(route('dealers.show', $withListing->dealerProfile))->assertOk()->assertSee('Verification pending');
});

it('never routes leads to owners or to dealers at capacity', function () {
    $full = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    $full->dealerProfile->forceFill(['lead_capacity' => 1])->save();
    $open = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    $owner = User::factory()->dealer(DealerStatus::Verified, [area('Gulberg')->id])->create();
    $owner->dealerProfile->forceFill(['business_type' => 'owner'])->save();

    $make = function () {
        $r = new BuyerRequirement(['purpose' => 'sale', 'city' => 'Lahore', 'area_ids' => [area('Gulberg')->id]]);
        $r->user_id = User::factory()->create()->id;
        $r->save();

        return app(LeadRouter::class)->routeRequirement($r, true);
    };

    $first = $make()->assignments->pluck('dealer_id');
    $second = $make()->assignments->pluck('dealer_id');

    expect($first)->toContain($full->id, $open->id)->not->toContain($owner->id)
        ->and($second)->toContain($open->id)->not->toContain($full->id);
});

/* Projects, area guides and locations ---------------------------------- */

it('publishes projects only when marked public', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.projects.store'), [
        'name' => 'Orchard Greens', 'city' => 'Lahore', 'status' => 'upcoming',
        'description' => 'A planned housing scheme with residential and commercial blocks.', 'is_published' => '0',
    ])->assertRedirect();

    $project = Project::sole();
    $this->get(route('projects.show', $project))->assertNotFound();

    $project->forceFill(['is_published' => true])->save();
    $this->get(route('projects.index'))->assertSee('Orchard Greens');
    $this->get(route('projects.show', $project))->assertOk()->assertSee('Upcoming');
});

it('shows area statistics only with enough asking prices', function () {
    $gulberg = area('Gulberg');
    Property::factory()->count(4)->create(['area_id' => $gulberg->id, 'price' => 40_000_000]);

    $this->get(route('areas.show', $gulberg))->assertOk()->assertSee('Not enough data yet (4 of 5');

    Property::factory()->create(['area_id' => $gulberg->id, 'price' => 50_000_000]);
    // 10 marla houses: median 40M / 10 marla = 40 lakh per marla.
    $this->get(route('areas.show', $gulberg))->assertSee('PKR 40 Lakh')->assertSee('5 listings');
});

it('lets admins add locations and blocks everyone else', function () {
    $this->actingAs(User::factory()->create())->post(route('admin.areas.store'), ['name' => 'X', 'city' => 'Lahore'])->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->post(route('admin.areas.store'), ['name' => 'DHA Phase 9 Prism', 'city' => 'Lahore', 'is_popular' => '1'])->assertRedirect();

    expect(area('DHA Phase 9 Prism'))->slug->toBe('dha-phase-9-prism-lahore')->is_popular->toBeTrue();
});

/* AI parsing ------------------------------------------------------------ */

it('understands spelled-out numbers and requested features', function () {
    $criteria = app(RulesRequirementParser::class)->parse('I want a 10 marla house in DHA Phase 6 with five bedrooms, a basement and parking, budget PKR 5 crore');

    expect($criteria->bedrooms)->toBe(5)
        ->and($criteria->amenities)->toEqualCanonicalizing(['basement', 'parking'])
        ->and($criteria->maxPrice)->toBe(50_000_000)
        ->and($criteria->type)->toBe(PropertyType::House);
});

it('uses a configured LLM for parsing and falls back to rules on failure', function () {
    config(['services.openai.key' => 'test-key']);
    Http::fakeSequence('*/chat/completions')
        ->push(['choices' => [['message' => ['content' => json_encode([
            'purpose' => 'rent', 'type' => 'apartment', 'areas' => ['Gulberg', 'Atlantis'], 'max_price' => 150000,
            'min_size' => null, 'bedrooms' => 2, 'bathrooms' => null, 'amenities' => ['lift', 'helipad'],
        ])]]]])
        ->push('down', 500);

    $criteria = app(LlmRequirementParser::class)->parse('flat chahiye');

    expect($criteria->purpose->value)->toBe('rent')
        ->and($criteria->areaIds)->toBe([area('Gulberg')->id])
        ->and($criteria->amenities)->toBe(['lift'])
        ->and($criteria->bedrooms)->toBe(2);

    // Second call fails: the rule-based result is used.
    expect(app(LlmRequirementParser::class)->parse('5 marla plot in Bahria Town')->type)->toBe(PropertyType::ResidentialPlot);
});

/* Drawn-area search ----------------------------------------------------- */

it('searches inside a drawn area, including concave shapes', function () {
    // An "L" shape: the notch at the top-right is outside the area.
    $poly = '31.40,74.20;31.60,74.20;31.60,74.30;31.50,74.30;31.50,74.40;31.40,74.40';
    Property::factory()->create(['title' => 'In The Stem', 'latitude' => 31.55, 'longitude' => 74.25]);
    Property::factory()->create(['title' => 'In The Foot', 'latitude' => 31.45, 'longitude' => 74.35]);
    Property::factory()->create(['title' => 'In The Notch', 'latitude' => 31.55, 'longitude' => 74.35]);
    Property::factory()->create(['title' => 'Far Away', 'latitude' => 31.10, 'longitude' => 74.00]);
    Property::factory()->create(['title' => 'No Location']);

    $this->get('/properties?poly='.urlencode($poly))
        ->assertSee('In The Stem')->assertSee('In The Foot')
        ->assertDontSee('In The Notch')->assertDontSee('Far Away')->assertDontSee('No Location')
        ->assertSee('Within drawn area');

    $this->get('/map?poly='.urlencode($poly))->assertOk()->assertSee('Clear Area')->assertSee('inside this drawn area');
});

it('ignores malformed drawn areas and prefers a drawn area over the viewport', function () {
    Property::factory()->create(['title' => 'Inside Shape', 'latitude' => 31.50, 'longitude' => 74.30]);
    Property::factory()->create(['title' => 'Unlocated Listing']);

    foreach (['31.5,74.3;31.6,74.4', 'abc;def;ghi', '91,74;31,74;31,75', str_repeat('31.5,74.3;', 101).'31.5,74.3'] as $bad) {
        $this->get('/properties?poly='.urlencode($bad))->assertOk()->assertSee('Unlocated Listing');
    }

    // Viewport that excludes the shape is ignored when a polygon is given.
    $this->get('/properties?north=10&south=9&east=10&west=9&poly='.urlencode('31.4,74.2;31.6,74.2;31.6,74.4;31.4,74.4'))
        ->assertSee('Inside Shape')->assertDontSee('Within map area');
});

it('saves a drawn area with a search and can clear it', function () {
    $buyer = User::factory()->create();
    $poly = '31.4,74.2;31.6,74.2;31.6,74.4';

    $this->actingAs($buyer)->post(route('account.saved-searches.store'), ['filters' => json_encode(['purpose' => 'sale', 'poly' => $poly])]);

    expect(SavedSearch::sole()->filters)->toBe(['purpose' => 'sale', 'poly' => $poly]);

    $search = new PropertySearch(['purpose' => 'sale', 'poly' => $poly]);
    $chip = collect($search->chips())->firstWhere('key', 'poly');
    $this->get('/properties?purpose=sale&poly='.urlencode($poly));
    expect($search->urlWithout($chip))->not->toContain('poly');
});
